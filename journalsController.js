const model = require("../models"),
		Journals = model.mariadb.journals,
		h = require("../helpers");

/**
 * Get a single journal
 * @since 1.0.0
 *
 * @param {object} req - The request object from Restify
 * @param {function} callback - The callback function to send the result to the router
 * @returns {function}
 */
const getJournal = (req, callback) => {
	const {params, user} = req;
	// Ensure we have a journal id
	if (!params.id) return callback(h.createError(400, "No journal id passed to the query."));
	// Create a new instance of our journal model; if we don't, we start building some pretty massive
	// queries, which is confusing and awful
	const journalModel = new Journals;
	journalModel
	.getJournals(user, params)
	.then(row => {
			if (!row || !row.length) return callback(h.createError(404, "No journals were found."));
			const journals = h.formatJournals(row);
			return callback(null, journals);
	}).catch(err => {
		return callback(h.createError(500, err));
	});
}

/**
 * Get a all journals a user is allowed to access
 * @since 1.0.0
 *
 * @param {object} req - The request object from Restify
 * @param {function} callback - The callback function to send the result to the router
 * @returns {function}
 */
const getJournals = (req, callback) => {
	const {params, user} = req;
	// Create a new instance of our journal model; if we don't, we start building some pretty massive
	// queries due to Knex not clearing queries between calls
	const journalModel = new Journals;
	journalModel
	.getJournals(user, params)
	.then(rows => {
			if (!rows || !rows.length) return callback(h.createError(404, "No journals were found."));
			const journals = h.formatJournals(rows);
			return callback(null, journals);
	}).catch(err => {
		return callback(h.createError(500, err));
	});
}

/**
 * Create a new journal
 * @since 1.0.0
 *
 * @param {object} req - The request object from Restify
 * @param {function} callback - The callback function to send the result to the router
 * @returns {function}
 */
const createJournal = (req, callback) => {
	const {params, user} = req;
	if (!h.isEmpty(params.journal_id)) {
		return callback(h.createError(405, "A journal ID was received with this request; please use the appropriate route for updating journals."));
	}
	// Create a new instance of our journal model; if we don't, we start building some pretty massive
	// queries due to Knex not clearing queries between calls
	const journalModel = new Journals;
	// If we need to get any saved details from inside the transaction, we need to
	// pass a storage object we can grab after the transactions have been committed,
	// since query results can't be passed back for all calls
	const journalInfo = {};
	journalModel.saveJournal(user, params, journalInfo).then(result => {
		return callback(null, { message: "Journal created", journal_id: journalInfo.journal_id });
	}).catch(err => {
		return callback(h.createError(500, err));
	});
}

/**
 * Update a journal
 * @since 1.0.0
 *
 * @param {object} req - The request object from Restify
 * @param {function} callback - The callback function to send the result to the router
 * @returns {function}
 */
const updateJournal = (req, callback) => {
	const {params, user} = req;
	if (h.isEmpty(params.journal_id)) {
		return callback(h.createError(400, "A journal ID was not received with this request; please provide one or use the appropriate route to create a new journal."));
	}
	// Create a new instance of our journal model; if we don't, we start building some pretty massive
	// queries due to Knex not clearing queries between calls
	const journalModel = new Journals;
	// If we need to get any saved details from inside the transaction, we need to
	// pass a storage object we can grab after the transactions have been committed,
	// since query results can't be passed back for all calls
	const journalInfo = {};
	journalModel.saveJournal(user, params).then(result => {
		return callback(null, h.message("Journal updated"));
	}).catch(err => {
		return callback(h.createError(500, err));
	});
}

module.exports = {
	getJournal,
	getJournals,
	createJournal,
	updateJournal
}