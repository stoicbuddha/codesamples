<?php

namespace App\Http\Controllers;

use App\Helpers\AccountHelper;
use Illuminate\Http\Request;
use App\User;
use App\Affiliates;
use App\Clicks;
use Cartalyst\Sentinel\Native\Facades\Sentinel;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;

class AffiliatesController extends Controller
{
    /**
     * Show the affiliate directory
     *
     * @return \Illuminate\Http\Response
     */
    public function directory()
    {
    	$affiliates_array = Affiliates::where('id', '>', 0)->orderBy('affiliate_name', 'ASC')->get();

        $affiliates = [];
        foreach($affiliates_array as $affiliate){
            $affiliate->user = User::where('id', $affiliate->user_id)->first();
            if(!empty($affiliate->user)) $affiliates[] = $affiliate;
        }

    	return view('affiliates.directory', compact('affiliates'));

    }

    /**
     * Show the profile of a specific affiliate
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function get_affiliate_by_id($id)
    {
    	$affiliate_user = Sentinel::findById($id);

        $affiliate = Affiliates::where('user_id', $id)->first();

        $user = Sentinel::getUser();

        if(empty($affiliate_user) || (int) $affiliate_user->account_type !== 2) abort(404);

        $affiliate->about = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $affiliate->about);

    	return view('affiliates.single', compact('affiliate', 'affiliate_user', 'user'));

    }

    /**
     * Show the profile of a specific affiliate
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function url_check($url)
    {

        $affiliate = Affiliates::where('affiliate_link', $url)->first();

        $result = new \stdClass;

        $result->exists = false;

        if(!empty($affiliate)) $result->exists = true;

        return json_encode($result);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function get_register($email = false)
    {
        return view('affiliates.register', compact('email'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function get_register_show()
    {
        return view('affiliates.register_show');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function post_register()
    {
        $input = Input::all();

        $credentials = [
            'login' => $input['affilliateEmailAddress'],
        ];

        $user = Sentinel::findByCredentials($credentials);

        if(!empty($user)){
            $exists = true;
            return redirect()->action('UsersController@get_login')->with('exists', $exists);
        }

        $acct = new AccountHelper;

        $result = $acct->registerAffiliate($input);

        if(!$result->success){
            // We don't need to return the password or token, since they are info we shouldn't be passing back
            unset($input['affilliatePassword']);
            unset($input['stripe_token']);
            return view('affiliates.register', compact('input', 'result'));
        }

        return view('affiliates.registration_complete', compact('result'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function post_register_show()
    {
        $input = Input::all();

        $credentials = [
            'login' => $input['affilliateEmailAddress'],
        ];

        $user = Sentinel::findByCredentials($credentials);

        if(!empty($user)){
            $exists = true;
            return redirect()->action('UsersController@get_login')->with('exists', $exists);
        }

        $acct = new AccountHelper;

        $result = $acct->registerAffiliate($input, true);
        if(!$result->success){
            // We don't need to return the password or token, since they are info we shouldn't be passing back
            unset($input['affilliatePassword']);
            unset($input['stripe_token']);
            return view('affiliates.register', compact('input', 'result'));
        }
        return view('affiliates.registration_complete', compact('result'));
    }

    /**
     * Process incoming affiliate click
     *
     * @return \Illuminate\Http\Response
     */
    public function affiliate_click($link)
    {

		$affiliate = Affiliates::where('affiliate_link', $link)->first();
        if(empty($affiliate)) return redirect()->action('UsersController@get_register');
		$click = new Clicks;
		$click->ip_address = $_SERVER['REMOTE_ADDR'];
		$click->affiliate_id = $affiliate->user_id;
		$click->save();
		session(['affiliate'=>$affiliate->user_id]);

        return redirect()->action('UsersController@get_register_sponsored');
    }

    public function export_conversions_csv()
    {

        $user = Sentinel::getUser();

        $table = User::where('sponsor', $user->id)->orderBy('id', 'DESC')->get();
        $filename = storage_path() . "/app/public/" . $user->id.date('m-d-Y-h-i-s', time())."conversions.csv";
        $handle = fopen($filename, 'w+');
        fputcsv($handle, array('Email', 'First Name', 'Last Name', 'Phone Number', 'Address', 'City', 'State', 'Zip', 'Date Registered'));

        foreach($table as $row) {
            fputcsv($handle, array($row->email, $row->first_name, $row->last_name, $row->phone, $row->address, $row->city, $row->state, $row->zip, date('m/d/Y', strtotime($row->created_at))));
        }

        fclose($handle);

        $headers = array(
            'Content-Type' => 'text/csv',
            'Content-Transfer-Encoding' => 'UTF-8'
        );

        return Response::download($filename, $user->id.date('m-d-Y-h-i-s', time())."conversions.csv", $headers);
    }

    public function search_info()
    {

        $input = Input::all();

        if(empty($input['affiliates'])) return json_encode(new \stdClass);

        $affiliates_array = [];
        foreach($input['affiliates'] as $aff){
            $affiliate = Affiliates::where('id', $aff)->first();
            $user = Sentinel::findById($affiliate->user_id);
            $new_affiliate = new \stdClass();
            $new_affiliate->affiliate_id = $user->id;
            $new_affiliate->affiliate_name = $affiliate->affiliate_name;
            $new_affiliate->affiliate_avatar = $user->avatar;
            $new_affiliate->affiliate_city = $user->city;
            $new_affiliate->affiliate_state = $user->state;
            $new_affiliate->affiliate_zip = $user->zip;
            $affiliates_array[] = $new_affiliate;
        }

        return json_encode($affiliates_array);

    }

    public function reindex(){
        $user = Sentinel::getUser();
        // Send user to log in if they are trying to create a journal but aren't logged in
        if (empty($user)) {
            return redirect()->action('UsersController@get_login');
        }
        // if($user->is_admin !== 1) abort(404);
        Affiliates::clearIndices();
        Affiliates::reindex();
    }

}
