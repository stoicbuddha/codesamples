<?php

class Client_Projects {

	private $project_table, $errors;
	public $dbconn;

	function __construct() {
		// No need to globalize this in every
		// single function in the class
		global $wpdb;
		$this->dbconn = $wpdb;
		// Make sure we have the table name set
		$this->project_table = $this->dbconn->prefix . 'client_projects';
		$this->schedule_table = $this->dbconn->prefix . 'client_schedules';
		$this->schedule_metadata_table = $this->dbconn->prefix . 'client_schedules_metadata';
		// Set up the errors array
		$this->errors = [];

	}

	// Add an error to our array for checking later
	function add_error($msg) {
		$this->errors[] = $msg;
	}

	// Get the last error we had; useful for debug
	// and letting a user know if something goes wrong
	function get_last_error() {
		return $this->errors[count($this->errors - 1)];
	}

	// Get the errors we had during a request; useful for
	// debug and letting a user know if something goes wrong
	function get_errors() {
		return $this->errors;
	}

	// Check if we have errors in our object; useful for
	// debug and letting a user know if something goes wrong
	function has_errors() {
		return count($this->errors) > 0;
	}

	// Get a project by ID
	function get_project_by_id(WP_REST_Request $request) {
		// Set the initial return status
		$status = 200;
		// Get the submitted params
		$params = json_decode(json_encode($request->get_params()));
		// Build our return object
		$return_obj = new stdClass;
		$return_obj->success = true;

		$project = $this->dbconn->get_results( "SELECT * FROM $this->project_table WHERE id = $params->id AND deleted = 0;" );
		$formatted_return = new stdClass;
		if (!empty($project)) {
			$formatted_return->id = $params->id;
			$formatted_return->name = $project[0]->name;
			$formatted_return->type = $project[0]->type;
			$formatted_return->address = $project[0]->address;
			$formatted_return->start_timestamp = $project[0]->start_timestamp;
		} else {
			$this->add_error("Project does not exist.");
		}
		// Set up the return object appropriately
		$return_obj->project = $formatted_return;
		// Format and return our response
		return client_format_return($this, $return_obj, $status);
	}

	// Get all projects
	function get_all_projects(WP_REST_Request $request) {
		// Set the initial return status
		$status = 200;
		// Get the submitted params
		$params = json_decode(json_encode($request->get_params()));
		// Build our return object
		$return_obj = new stdClass;
		$return_obj->success = true;
		// Determine if we are including completed projects or not
		$completed = !empty($params->completed) && $params->completed == 1 ? "" : " AND completed = 0";
		// Start building our projects array
		$projects_array = [];
		$projects = $this->dbconn->get_results( "SELECT * FROM $this->project_table WHERE deleted = 0 $completed ORDER BY start_timestamp ASC;" );
		if (!empty($projects)) {
			foreach($projects as $project) {
				$formatted_return = new stdClass;
				$formatted_return->id = $project->id;
				$formatted_return->name = $project->name;
				$formatted_return->address = $project->address;
				$formatted_return->start_timestamp = $project->start_timestamp;
				$formatted_return->completed = !empty($project->completed) ? true : false;
				$projects_array[] = $formatted_return;
			}
		} else {
			$this->add_error("No projects found");
			$status = 404;
		}
		// Set up the return object appropriately
		$return_obj->projects = $projects_array;
		// Format and return our response
		return client_format_return($this, $return_obj, $status);
	}

	// Create a project
	function create_project(WP_REST_Request $request) {
		// Set the initial return status
		$status = 200;
		// Get the submitted params
		$params = json_decode(json_encode($request->get_params()));
		// Build our return object
		$return_obj = new stdClass;
		$return_obj->success = true;

		if (client_get_role() != "ec_admin") {
			// The user is not an admin and isn't allowed to create projects
			$this->add_error("Current user does not have permission to create projects.");
			$status = 403;
		}
		if (empty($params->name)) {
			$this->add_error("No name given for project.");
			$status = 400;
		}
		if (empty($params->address)) {
			$this->add_error("No address given for project.");
			$status = 400;
		}
		if (empty($params->start_timestamp)) {
			$this->add_error("No start time given for project.");
			$status = 400;
		}
		if (!$this->has_errors()) {
			$result = $this->dbconn->insert(
				$this->project_table,
				array(
					'name' => $params->name,
					'address' => $params->address,
					'start_timestamp' => $params->start_timestamp
				),
				array(
					'%s',
					'%s',
					'%s',
					'%d'
				)
			);
			if (empty($result)) {
				$this->add_error("Undetermined error saving project.");
				$status = 500;
			} else {
				$return_obj->id = $this->dbconn->insert_id;
				$project = new stdClass;
				$project->id = $return_obj->id;
				$project->name = $params->name;
				$project->start_timestamp = $params->start_timestamp;
				// Create the initial project
				client_create_parent_schedule($this, $project, 0, null, 0, $params->name);
			}
		}
		// Format and return our response
		return client_format_return($this, $return_obj, $status);
	}

	// update a project
	function update_project(WP_REST_Request $request) {
		// Set the initial return status
		$status = 200;
		// Get the submitted params
		$params = json_decode(json_encode($request->get_params()));
		// Build our return object
		$return_obj = new stdClass;
		$return_obj->success = true;

		if (client_get_role() != "ec_admin") {
			// The user is not an admin and isn't allowed to create projects
			$this->add_error("Current user does not have permission to create projects.");
			$status = 403;
		}
		if (empty($params->id)) {
			$this->add_error("No id given for project.");
			$status = 400;
		}
		if (empty($params->name)) {
			$this->add_error("No name given for project.");
			$status = 400;
		}
		if (empty($params->type)) {
			$this->add_error("No type given for project.");
			$status = 400;
		}
		if (empty($params->address)) {
			$this->add_error("No address given for project.");
			$status = 400;
		}
		if (empty($params->start_timestamp)) {
			$this->add_error("No start time given for project.");
			$status = 400;
		}
		if (!$this->has_errors()) {
			$result = $this->dbconn->update(
				$this->project_table,
				array(
					'name' => $params->name,
					'type' => $params->type,
					'address' => $params->address,
					'start_timestamp' => $params->start_timestamp
				),
				array("id" => $params->id),
				array(
					'%s',
					'%s',
					'%s',
					'%d'
				)
			);
			if ($result === false) {
				$this->add_error("Undetermined error updating project.");
				$status = 500;
			} else {
				// $return_obj->id = $this->dbconn->insert_id;
			}
		}
		// Format and return our response
		return client_format_return($this, $return_obj, $status);
	}

	// "Complete" a project; mark it as completed in the DB for filtration purposes
	function complete_project(WP_REST_Request $request) {
		// Set the initial return status
		$status = 200;
		// Get the submitted params
		$params = json_decode(json_encode($request->get_params()));
		// Build our return object
		$return_obj = new stdClass;
		$return_obj->success = true;

		if (client_get_role() != "ec_admin") {
			// The user is not an admin and isn't allowed to manipulate projects
			$this->add_error("Current user does not have permission to edit projects.");
			$status = 403;
		}
		if (empty($params->id)) {
			$this->add_error("No id given for project.");
			$status = 400;
		}
		if (!$this->has_errors()) {
			$result = $this->dbconn->update(
				$this->project_table,
				array(
					'completed' => 1,
				),
				array("id" => $params->id),
				array(
					'%d'
				)
			);
			if ($result === false) {
				$this->add_error("Undetermined error deleting project.");
				$status = 500;
			} else {
				$return_obj->id = $this->dbconn->insert_id;
			}
		}
		// Format and return our response
		return client_format_return($this, $return_obj, $status);
	}

	// "Delete" a project; mark it as deleted in the DB but don't actually remove it
	function delete_project(WP_REST_Request $request) {
		// Set the initial return status
		$status = 200;
		// Get the submitted params
		$params = json_decode(json_encode($request->get_params()));
		// Build our return object
		$return_obj = new stdClass;
		$return_obj->success = true;

		if (client_get_role() != "ec_admin") {
			// The user is not an admin and isn't allowed to create projects
			$this->add_error("Current user does not have permission to create projects.");
			$status = 403;
		}
		if (empty($params->id)) {
			$this->add_error("No id given for project.");
			$status = 400;
		}
		if (!$this->has_errors()) {
			$result = $this->dbconn->update(
				$this->project_table,
				array(
					'deleted' => 1,
				),
				array("id" => $params->id),
				array(
					'%d'
				)
			);
			if ($result === false) {
				$this->add_error("Undetermined error deleting project.");
				$status = 500;
			} else {
				$return_obj->id = $this->dbconn->insert_id;
			}
		}
		// Format and return our response
		return client_format_return($this, $return_obj, $status);
	}
}