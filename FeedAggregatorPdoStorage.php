<?php

class FeedAggregatorPdoStorage {
	// Database connection
	var $db;

	// Database schema
	var $schema;
	
	// Prepared statement cache
	var $stmCache = array();
	
	// Configuration
	var $config = array(
		'datasource' => 'sqlite:/tmp/tmp-feed-aggregator.db'
	);
	
	
	/**
		__construct: initialising storage
		@param config array, an optional configuration hash
	**/
	public function __construct($config=false) {
		if ($config) {
			$this->setConfig($config);
		}
	}
	
	/**
		setConfig: updates the configuration with the supplied hash of configuration 
			settings.
		@param config array
	**/
	public function setConfig($config) {
		if (is_array($config)) {
			$this->config = array_merge($this->config, $config);
		}
	}

	##
	## Feed table
	##

	/**
		addFeed: adds a new Feed to the aggregator
		@param a feed object
		@returns boolean whether the feed was added and doesn't already exist
	**/
	public function addFeed($feed) {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('feed', 'add');
		$stm->execute(array(
			':url'         => $feed->url,
			':title'       => $feed->title,

			':type'        => (!empty($feed->type))?$feed->type:'F',
			':status'      => (!empty($feed->status))?$feed->status:'A',
			':created'     => time(),
			':lastUpdated' => 0,
			':lastPolled'  => 0,
			':nextPoll'    => 0
		));

		if ($this->_isPdoError($stm)) {
			return false;
		}		
		
		return true;
	}
	
	/**
		updateFeed: updates an existing Feed to the aggregator
		@param a feed object
		@returns boolean whether the feed was updated
	**/
	public function updateFeed($feed) {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('feed', 'updatePoll');
		$stm->execute(array(
			':id'          => $feed->id,
			':lastUpdated' => $feed->lastUpdated,
			':lastPolled'  => $feed->lastPolled
		));

		if ($this->_isPdoError($stm)) {
			return false;
		}		
		
		return true;
	}

	/**
		getFeeds: returns all the feeds the aggregator has saved
		@returns an array of Feed objects
	**/
	public function getFeeds() {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('feed', 'getAll');		
		$stm->execute();
		
		if ($this->_checkPdoError($stm)) {
			return NULL;
		}

		$feeds = array();
		while($row = $stm->fetchObject()) {
			$feeds[] = $row;
		}
		return $feeds;
	}
	
	/**
		isFeed: checks to see if a feed exists, given it's URL
		@param the url of the feed
		@returns boolean whether the feed exists or not
	**/
	public function isFeed($url) {
		$feed = $this->getFeed($url);
		return !empty($feed->url);
	}
	
	/**
		getFeed: gets the feed data for the specified URL
		@param the url of the feed
		@returns a Feed object, or NULL
	**/
	public function getFeed($url) {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('feed', 'getByUrl');		
		$stm->execute(array(
			':url' => $url
		));
		
		if ($this->_checkPdoError($stm)) {
			return NULL;
		}

		if ($feed = $stm->fetchObject()) {
			return $feed;
		}	
		return NULL;
	}

	/**
		getFeedById: gets the feed by it's primary key
		@param id of the feed
		@returns a Feed object, or NULL
	**/
	public function getFeedById($id) {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('feed', 'getById');		
		$stm->execute(array(
			':id' => $id
		));
		
		if ($this->_checkPdoError($stm)) {
			return NULL;
		}

		if ($feed = $stm->fetchObject()) {
			return $feed;
		}	
		return NULL;
	}
	
	/**
		deleteFeed: deletes the feed associated with the specified URL
		@param url of the feed
		@returns boolean whether the deletion of the feed was successful or not
	**/
	public function deleteFeed($feed) {
		$this->_initDbConnection();
		
		if (is_string($feed)) {
			$stm = $this->_prepareStatement('feed', 'deleteByUrl');		
			$stm->execute(array(
				':url' => $feed
			));
		} else if (!empty($feed->id)) {
			$stm = $this->_prepareStatement('feed', 'deleteById');		
			$stm->execute(array(
				':id' => $feed->id
			));
		}
			
		if ($this->_checkPdoError($stm)) {
			return false;
		}

		if ($stm->rowCount()) {
			return true;
		}	
		return false;
	}

	##
	## Author table
	##

	/**
		addAuthor: adds a new Author object to the aggregator
		@param an Author object
		@returns boolean whether the Author was saved or not
	**/
	public function addAuthor($author) {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('author', 'add');
		$stm->execute(array(
			':name'  => $author->name,
			':url'   => (!empty($author->url))?$author->url:'',
			':email' => (!empty($author->email))?$author->email:''
		));

		if ($this->_isPdoError($stm)) {
			return false;
		}		
		
		return true;
	}

	/**
		getAuthors: gets all the stored authors
		@returns an Array of Authors
	**/
	public function getAuthors() {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('author', 'getAll');		
		$stm->execute();
		
		if ($this->_checkPdoError($stm)) {
			return NULL;
		}

		$authors = array();
		while($row = $stm->fetchObject()) {
			$authors[] = $row;
		}
		return $authors;
	}
	
	/**
		isAuthor: returns whether an Author exists with the specified name
		@param Author name
		@returns true/false whether the Author by that name exists.
	**/
	public function isAuthor($name) {
		$author = $this->getAuthor($name);
		return !empty($author->name);
	}

	/**
		getAuthorById: gets the Author by their database id / foreign key
		@param id
		@returns Author, or NULL
	**/
	public function getAuthorById($id) {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('author', 'getById');		
		$stm->execute(array(
			':id' => $id
		));
		
		if ($this->_checkPdoError($stm)) {
			return NULL;
		}

		if ($author = $stm->fetchObject()) {
			return $author;
		}	
		return NULL;
	}
	
	/**
		getAuthor: returns the Author object based on the supplied name
		@param author name
		@returns Author, or NULL
	**/
	public function getAuthor($name) {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('author', 'getByName');		
		$stm->execute(array(
			':name' => $name
		));
		
		if ($this->_checkPdoError($stm)) {
			return NULL;
		}

		if ($author = $stm->fetchObject()) {
			return $author;
		}	
		return NULL;
	}

	/**
		deleteAuthor: deletes the Author object given either an existing author object, or
			the author name
		@param Author name, or an existing Author object
		@returns boolean whether the deletion was successful or not.
	**/
	public function deleteAuthor($author) {
		$this->_initDbConnection();
		
		if (is_string($author)) {
			$stm = $this->_prepareStatement('author', 'deleteByName');		
			$stm->execute(array(
				':name' => $author
			));
		} else if (!empty($author->id)) {
			$stm = $this->_prepareStatement('author', 'deleteById');		
			$stm->execute(array(
				':id' => $author->id
			));
		}
			
		if ($this->_checkPdoError($stm)) {
			return false;
		}

		if ($stm->rowCount()) {
			return true;
		}	
		return false;
	}
	
	##
	## Entry table
	##

	/**
		addEntry: adds a new Entry
		@param Entry object
		@returns boolean whether the insertion was successful or not
	**/
	public function addEntry($entry) {
		$this->_initDbConnection();
		
		// TODO: convert author into authorid
		$authorId = 0;
		
		if ($entry->author) {
			$authorId = $this->_getAuthorId($entry->author);
		}
		
		$stm = $this->_prepareStatement('entry', 'add');
		$stm->execute(array(
			':url'       => $entry->url,
			':title'     => $entry->title,
			':id'        => $entry->id,
			
			':author_id' => $authorId,
			':summary'   => (!empty($entry->summary))?$entry->summary:'',
			':content'   => (!empty($entry->content))?$entry->content:'',
			
			':published' => strtotime($entry->published),
			':updated'   => (!empty($entry->updated))?strtotime($entry->updated):0

		));

		if ($this->_isPdoError($stm)) {
			return false;
		}		
		
		return true;
	}

	/**
		getEntries: gets all the stored entries
		@returns an Array of Entries
	**/
	public function getEntries() {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('entry', 'getAll');		
		$stm->execute();
		
		if ($this->_checkPdoError($stm)) {
			return NULL;
		}

		$entries = array();
		while($row = $stm->fetchObject()) {
			$row = $this->_hydrateEntry($row);
			$entries[] = $row;
		}
		return $entries;
	}
	
	/**
		isEntry: returns whether an entry with the specified atom:id exists
		@param an Atom Id
		@returns boolean whether this entry exists or not
	**/
	public function isEntry($id) {
		$entry = $this->getEntry($id);
		return !empty($entry->id);
	}

	/**
		getEntryById: returns an entry based on the primary key/foreign key
		@param the entry id
		@returns an Entry object, or NULL
	**/
	public function getEntryById($row_id) {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('entry', 'getById');
		$stm->execute(array(
			':row_id' => $row_id
		));
		
		if ($this->_checkPdoError($stm)) {
			return NULL;
		}

		if ($entry = $stm->fetchObject()) {
			$entry = $this->_hydrateEntry($entry);
			return $entry;
		}	
		return NULL;
	}
	
	/**
		getEntry: returns an Entry based on the specified atom:id
		@param the Atom Id of the entry
		@returns an Entry object, or NULL
	**/
	public function getEntry($id) {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('entry', 'getByAtomId');		
		$stm->execute(array(
			':id' => $id
		));
		
		if ($this->_checkPdoError($stm)) {
			return NULL;
		}

		if ($entry = $stm->fetchObject()) {
			$entry = $this->_hydrateEntry($entry);
			return $entry;
		}	
		return NULL;
	}
	
	/**
		deleteEntry: delete entry that corresponds to the specified atom:id or Entry object
		@param Entry Atom Id, or an existing Entry object
		@returns boolean whether the deletion was successful or not.
	**/
	public function deleteEntry($entry) {
		$this->_initDbConnection();
		
		if (is_string($entry)) {
			$stm = $this->_prepareStatement('entry', 'deleteByAtomId');		
			$stm->execute(array(
				':id' => $entry
			));
		} else if (!empty($entry->row_id)) {
			$stm = $this->_prepareStatement('entry', 'deleteById');		
			$stm->execute(array(
				':row_id' => $entry->row_id
			));
		}
			
		if ($this->_checkPdoError($stm)) {
			return false;
		}

		if ($stm->rowCount()) {
			return true;
		}	
		return false;
	}
	
	##
	## FeedEntry table
	##
	
	public function addFeedEntry($feed, $entry) {
		$this->_initDbConnection();
		
		// TODO: convert author into authorid
		$entryId = $this->_getEntryRowId($entry);
		//echo "Adding feedEntry [{$feed->id}:{$entryId}]\n";

		$stm = $this->_prepareStatement('feedentry', 'add');
		$stm->execute(array(
			':feed_id'  => $feed->id,
			':entry_id' => $entryId,
			':created'  => time()
		));

		if ($this->_isPdoError($stm)) {
			return false;
		}		
		
		return true;
		
	}
	
	public function getFeedEntries($feedUrl, $maxItems=false) {
		$this->_initDbConnection();
		
		$stm = $this->_prepareStatement('feedentry', 'getByFeedUrl');		
		$stm->execute(array(
			':feed_url' => $feedUrl
		));
		
		if ($this->_checkPdoError($stm)) {
			return NULL;
		}

		$entries   = array();
		$itemCount = 0;
		while($row = $stm->fetchObject()) {
			//echo '@';
			$row = $this->_hydrateEntry($row);
			$entries[] = $row;
			$itemCount++;
			if ($maxItems && $maxItems==$itemCount) {
				break;
			}
		}
		return $entries;
	}
	

	####################################################################
	##
	## Private and protected methods
	##
	
	/**
		_hydrateEntry: Takes an entry object recently retrieved from storage
			and hydrates it, bringing in the author details, and formatting
			dates into ISO8601 format.
		@param an Entry row
		@returns a hydrated Entry object
	**/
	protected function _hydrateEntry($entry) {
		//echo "Hydrating: "; print_r($entry);
		
		if ($entry->published && is_numeric($entry->published)) {
			$entry->published = date('c', $entry->published);
		}

		if ($entry->updated && is_numeric($entry->updated) && $entry->updated>0) {
			$entry->updated = date('c', $entry->updated);
		}
		
		if ($entry->author_id) {
			$entry->author = $this->getAuthorById($entry->author_id);
			//echo "Hydrating author: "; print_r($entry->author);
			unset($entry->author_id);
		}
		
		return $entry;
	}
	
	/**
		_getEntryRowId: returns the entry row_id for the specified entry object.
			If none found, the function stores the entry and returns it's row_id.
			Or returns 0 if this fails.
		@param an Entry object
		@returns the Entry row_id, or 0 if none could be found/created
	**/
	protected function _getEntryRowId($entry) {
		if (!empty($entry->row_id)) {
			return $entry->row_id;
		} else {
			$this->addEntry($entry);
			$storedEntry = $this->getEntry($entry->id);
			
			if (!empty($storedEntry->row_id)) {
				return $storedEntry->row_id;
			}
		}
		return 0;
	}

	/**
		_getAuthorId: returns the author id for the specified author object.
			If none found, the function stores the author and returns it's key.
			Or returns 0 if this fails.
		@param an Author object
		@returns the Author id, or 0 if none could be found/created
	**/
	protected function _getAuthorId($author) {
		if (!empty($author->id)) {
			return $author->id;
		} else {
			$this->addAuthor($author);
			$storedAuthor = $this->getAuthor($author->name);
			//echo "Stored Author: "; print_r($storedAuthor);
			
			if (!empty($storedAuthor->id)) {
				return $storedAuthor->id;
			}
		}
		return 0;
	}
	
	/**
		_initDbConnection: lazy initialisation of connection and database. 
			initialises connection in $this->conn, or exits. Checks that 
			all the database tables exists, creating them along the way.
	**/
	protected function _initDbConnection() {
		if (!empty($this->db)) {
			return;
		}
		
		// Create a new connection
		if (empty($this->config['datasource'])) {
			die("FeedAggregatorPdoStorage: no datasource configured\n");
		}

		// Initialise a new database connection and associated schema.
		$db = new PDO($this->config['datasource']); 
		$this->_initDbSchema();
		
		// Check the database tables exist
		$this->_initDbTables($db);

		// Database successful, make it ready to use
		$this->db = $db;
	} 

	/**
		_initDbTables: lazy creates missing db tables based on those
			specified in the schame
		
		@param $db - connection to a db
	**/
	protected function _initDbTables($db) {
		$tables = array_keys($this->schema);
		$buffer = array();
		foreach($this->schema as $sql) {
			$buffer[] = $sql['create'];
		}
		$db->exec(implode("\n", $buffer));

		// Check for fatal failures?
		if ($db->errorCode() !== '00000') {
			$info = $db->errorInfo();
			die('FeedAggregatorPdoStorage->_initDbTables: PDO Error: ' . 
				implode(', ', $info) . "\n");
		}
	}
	
	/**
		_isPdoError: quiet check for a PDO error. Returns true/false whether an 
			error occurred or not.
		@param a PDO statement
		@returns boolean whether an error occurred or not
	**/
	protected function _isPdoError($stm) {
		// Check for errors
		if ($stm->errorCode() !== '00000') {
			return true;
		}
		return false;
	}
	
	/**
		_checkPdoError: checks the PDO statement for an error, and displays
			a message before returning true/false whether an error occurred.
		@param a PDO statement
		@returns boolean whether an error occurred or not
	**/
	protected function _checkPdoError($stm) {
		// Check for fatal failures?
		if ($stm->errorCode() !== '00000') {
			$info = $stm->errorInfo();
			echo 'PDO Error: ' . implode(', ', $info) . "\n";
			return true;
		}
		return false;
	}
	
	/**
		_prepareStatement: lazy cache of prepared statements, so the prepare
			statement is only done once in the current instantiation, and 
			reused wherever possible.
		@param table name (from the schema)
		@param query name (from the schema)
		@returns a prepared PDO Statement
	**/
	protected function _prepareStatement($table, $queryKey) {
		$cacheKey = "{$table}:{$queryKey}";
		if (empty($this->stmCache[$cacheKey])) {
			$stm = $this->db->prepare($this->schema[$table][$queryKey]);	
			$this->stmCache[$cacheKey] = $stm;
		} else {
			// Cache hit!
			//echo '±';
		}
		return $this->stmCache[$cacheKey];
	}

	/**
		_initDbSchema: lazily initialises the $this->schema array 
			with the database schema currently in use. Values should 
			be using Prepared query syntax.
	**/
	protected function _initDbSchema() {
		if (!empty($this->schema)) {
			return;
		}
		
		#################################################################
		#
		# TABLE: feed
		#
		
		$this->schema['feed']['create'] = <<<SQL
CREATE TABLE IF NOT EXISTS `feed` (
	id          INTEGER PRIMARY KEY AUTOINCREMENT,
	url         VARCHAR(255) UNIQUE,
	title       VARCHAR(255),
	
	type        VARCHAR(1),
	status      VARCHAR(1),
	created     DATETIME,
	lastUpdated DATETIME,

	lastPolled  DATETIME,
	nextPoll    DATETIME
);
SQL;

		$this->schema['feed']['add'] = <<<SQL
INSERT INTO `feed` 
(id, url, title, type, status, created, lastUpdated, lastPolled, nextPoll)
VALUES
(NULL, :url, :title, :type, :status, 
 :created, :lastUpdated, :lastPolled, :nextPoll);
SQL;

		$this->schema['feed']['updatePoll'] = <<<SQL
UPDATE `feed` 
SET
	lastUpdated = :lastUpdated,
	lastPolled  = :lastPolled
WHERE
	id = :id
SQL;

		$this->schema['feed']['getAll'] = <<<SQL
SELECT * FROM `feed`;
SQL;

		$this->schema['feed']['getById'] = <<<SQL
SELECT * FROM `feed`
WHERE
	id = :id;
SQL;

		$this->schema['feed']['getByUrl'] = <<<SQL
SELECT * FROM `feed`
WHERE
	url = :url;
SQL;

		$this->schema['feed']['deleteById'] = <<<SQL
DELETE FROM `feed`
WHERE
	id = :id;
SQL;

		$this->schema['feed']['deleteByUrl'] = <<<SQL
DELETE FROM `feed`
WHERE
	url = :url;
SQL;

		#################################################################
		#
		# TABLE: author
		#

		$this->schema['author']['create'] = <<<SQL
CREATE TABLE IF NOT EXISTS `author` (
	id    INTEGER PRIMARY KEY AUTOINCREMENT,
	name  VARCHAR(255),
	url   VARCHAR(255),
	email VARCHAR(255),
	
	UNIQUE(name, url)
);
SQL;

		$this->schema['author']['add'] = <<<SQL
INSERT INTO `author`
(id, name, url, email)
VALUES
(NULL, :name, :url, :email)
SQL;

		$this->schema['author']['getAll'] = <<<SQL
SELECT * FROM `author`;
SQL;

		$this->schema['author']['getByName'] = <<<SQL
SELECT * FROM `author`
WHERE
	name = :name;
SQL;

		$this->schema['author']['getById'] = <<<SQL
SELECT * FROM `author`
WHERE
	id = :id;
SQL;

		$this->schema['author']['deleteByName'] = <<<SQL
DELETE FROM `author`
WHERE
	name = :name;
SQL;

		$this->schema['author']['deleteById'] = <<<SQL
DELETE FROM `author`
WHERE
	id = :id;
SQL;


		#################################################################
		#
		# TABLE: entry
		#
		
		$this->schema['entry']['create'] = <<<SQL
CREATE TABLE IF NOT EXISTS `entry` (
	row_id      INTEGER PRIMARY KEY AUTOINCREMENT,
	url         VARCHAR(255),
	title       VARCHAR(255),
	id          VARCHAR(255) UNIQUE,
	author_id   INTEGER,
	summary     TEXT,
	content     TEXT,
	published   DATETIME,
	updated     DATETIME

);
SQL;

		$this->schema['entry']['add'] = <<<SQL
INSERT INTO `entry`
(row_id, url, title, id, author_id, summary, content, published, updated)
VALUES
(NULL, :url, :title, :id, :author_id, :summary, :content, :published, :updated)
SQL;

		$this->schema['entry']['getAll'] = <<<SQL
SELECT * FROM `entry`;
SQL;

		// TODO: Add join for author id
		$this->schema['entry']['getById'] = <<<SQL
SELECT * FROM `entry`
WHERE
	row_id = :row_id;
SQL;

		// TODO: Add join for author id
		$this->schema['entry']['getByAtomId'] = <<<SQL
SELECT * FROM `entry`
WHERE
	id = :id;
SQL;

		$this->schema['entry']['deleteById'] = <<<SQL
DELETE FROM `entry`
WHERE
	row_id = :row_id;
SQL;

		$this->schema['entry']['deleteByAtomId'] = <<<SQL
DELETE FROM `entry`
WHERE
	id = :id;
SQL;



		#################################################################
		#
		# TABLE: feedentry
		#
		
		$this->schema['feedentry']['create'] = <<<SQL
CREATE TABLE IF NOT EXISTS `feedentry` (
	feed_id  INTEGER,
	entry_id INTEGER,
	created  DATETIME,

	PRIMARY KEY(feed_id, entry_id)	
);
SQL;

	$this->schema['feedentry']['add'] = <<<SQL
INSERT INTO `feedentry`
(feed_id, entry_id, created)
VALUES
(:feed_id, :entry_id, :created)
SQL;

	$this->schema['feedentry']['getByFeedUrl'] = <<<SQL
SELECT 
	row_id, entry.url, entry.title, entry.id, 
	author_id, summary, content,
	entry.published, entry.updated
FROM entry
LEFT JOIN feedentry ON entry.row_id      = feedentry.entry_id
LEFT JOIN feed      ON feedentry.feed_id = feed.id
WHERE
	feed.url = :feed_url
ORDER BY entry.published DESC
SQL;


	}

}

?>