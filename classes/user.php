<?php
/**
 * Created by PhpStorm.
 * User: rg12
 * Date: 02/05/2017
 * Time: 13:23
 */

namespace claremcr\clareevents\classes;
use claremcr\clareevents\classes\genericitem;

/**
 * User class
 *
 * @abstract The User class, controls access, user permissions and stores
 * the user's crsid
 * @description Not much more to say than the abstract really
 */
class user extends genericitem {

	# User associated variables
	protected $id;
	protected $crsid;
	protected $e_view;
	protected $e_book;
	protected $e_adm;
	protected $s_adm;
	protected $p_view;
	protected $p_book;
	protected $p_adm;
	protected $mcr_member;
	protected $associate_member;
	protected $cra;
	protected $college_bill;
	protected $type;
	protected $enabled;
	protected $name;
	protected $eventslist;
	protected $bookingdetails;
	protected $bookings;
	protected $queue;
	protected $queuedetails;
	protected $logger;

	function __construct() {
		parent::__construct();
		global $logger;
		$this->logger = &$logger;
		$this->logger->debug("setting variables");
		$this->eventslist     = $this->my_pre . 'eventslist';
		$this->bookingdetails = $this->my_pre . 'booking_details';
		$this->bookings       = $this->my_pre . 'booking';
		$this->queue          = $this->my_pre . 'queue';
		$this->queuedetails   = $this->my_pre . 'queue_details';
		$this->logger->info("User created");
	}

	/**
	 * has_perm function
	 * @abstract Checks whether user has permission to perform an action
	 *
	 * @param string $perm
	 *
	 * @return bool True/False
	 */
	function has_perm( $perm ) {
		$this->logger->debug($perm);
		if ( $this->$perm != 1 ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * @param $crsid
	 *
	 * @return bool
	 */
	function getFromCRSID( $crsid ) {


		# See whether user exists
		if ( ! $this->exists( $crsid ) ) {
			echo "<p>User " . $crsid . " does not exist.</p>\n";
			$this->logger->error("user doesn't exist");
			return false;
		}

		# Prepare the statement and execute
		$this->db->query( "SELECT * FROM access WHERE crsid=:crsid" );
		$this->db->bind( ":crsid", $crsid );

		# Set the fetch mode to pull the variables into this instance
		$results = $this->db->single();
		foreach($results as $key => $value){
			$this->{$key} = $value;
		}
		$this->logger->debug("user data",$results);
		# Get the username from LDAP
		# ldap lookup the user's name, and if we can, put their name into the name field
		$this->logger->info("Users CRSID=".$this->crsid);
		$ds      = ldap_connect( "ldap.lookup.cam.ac.uk" );
		$lsearch = ldap_search( $ds, "ou=people,o=University of Cambridge,dc=cam,dc=ac,dc=uk", "uid=" . $this->crsid . "" );
		$info    = ldap_get_entries( $ds, $lsearch );
		$name    = $info[0]["cn"][0];

		if ( $name == "" ) {
			$this->name=$this->crsid;
		} else {
			$this->name=$name;
		}
		$this->logger->info("name=".$this->name);
		return true;
	}

	# Grabs all the vars from the database of an existing user

	/**
	 * exists function
	 * @abstract Checks whether user exists by checking crsid against the db
	 * @global $dbh
	 *
	 * @param string $crsid
	 *
	 * @return bool True/False
	 */
	function exists( $crsid ) {
		$this->db->query( "SELECT COUNT(*) FROM access WHERE crsid=:crsid" );
		$this->db->bind( ":crsid", $crsid );
		$result = $this->db->single();

		if ( $result ) {
			return true;
		} else {
			return false;
		}
	}

	# Commits the user information to the database.
	# Note this cannot change s_adm status, only events related stuff.

	function commit_events_user() {

		$this->db->query( "UPDATE access SET e_view=:e_view, e_book=:e_book, e_adm=:e_adm, mcr_member=:mcr_member, associate_member=:associate, cra=:cra, enabled=:enabled WHERE id=:id" );
		$this->db->bind( ':e_view', $this->e_view );
		$this->db->bind( ':e_book', $this->e_book );
		$this->db->bind( ':e_adm', $this->e_adm );
		$this->db->bind( ':enabled', $this->enabled );
		$this->db->bind( ':id', $this->id );
		$this->db->bind( ':mcr_member', $this->mcr_member );
		$this->db->bind( ':associate', $this->associate_member );
		$this->db->bind( ':cra', $this->cra );

		try {
			$this->db->execute();
		} catch ( \PDOException $e ) {
			echo $e->getMessage();
		}
	}

	function commit_punts_user() {
		$this->db->query( "UPDATE access SET p_view=:p_view, p_book=:p_book, p_adm=:p_adm, enabled=:enabled WHERE id=:id" );
		$this->db->bind( ':p_view', $this->p_view );
		$this->db->bind( ':p_book', $this->p_book );
		$this->db->bind( ':p_adm', $this->p_adm );
		$this->db->bind( ':enabled', $this->enabled );
		$this->db->bind( ':id', $this->id );

		try {
			$this->db->execute();
		} catch ( \PDOException $e ) {
			echo $e->getMessage();
		}
	}

	function create_punts_user() {

		# Creates a user, with the perms set as per user input

		# Check that user doesnt exist:
		if ( $this->exists( $this->getValue( 'crsid' ) ) ) {
			echo "<p>User " . $this->getValue( 'crsid' ) . " EXISTS</p>\n";

			return false;
		}


		$this->db->query( "INSERT INTO access (crsid,p_view,p_book,p_adm,type,s_adm,enabled) VALUES (:crsid,:p_view,:p_book,:p_adm,:type,:s_adm,:enabled)" );
		$this->db->bind( ':crsid', $this->getValue( 'crsid' ) );
		$this->db->bind( ':p_view', $this->getValue( 'p_view' ) );
		$this->db->bind( ':p_book', $this->getValue( 'p_book' ) );
		$this->db->bind( ':p_adm', $this->getValue( 'p_adm' ) );
		$this->db->bind( ':s_adm', '0' );
		$this->db->bind( ':type', '1' );
		$this->db->bind( ':enabled', $this->getValue( 'enabled' ) );

		try {
			$this->db->execute();
		} catch ( \PDOException $e ) {
			echo $e->getMessage();
		}

		echo "<p>User " . $this->crsid . " CREATED.</p>\n";

		return true;
	}

	# Creates a new user and commits them to the database.
	function create_user() {

		# Creates a user, with the perms set as per user input

		# Check that user doesnt exist:
		if ( $this->exists( $this->getValue( 'crsid' ) ) ) {
			echo "<p>User " . $this->getValue( 'crsid' ) . " EXISTS</p>\n";

			return false;
		}

		# Prepare the statement and bind the given values
		$this->db->query( "INSERT INTO access (crsid,e_view,e_book,e_adm,mcr_member,associate_member,cra,s_adm,enabled) VALUES (:crsid,:e_view,:e_book,:e_adm,:mcr_member,:asociate_member,:cra,:s_adm,:enabled)" );
		$this->db->bind( ':crsid', $this->crsid );
		$this->db->bind( ':e_view', $this->e_view );
		$this->db->bind( ':e_book', $this->e_book );
		$this->db->bind( ':e_adm', $this->e_adm );
		$this->db->bind( ':s_adm', '0' );
		$this->db->bind( ':enabled', $this->enabled );
		$this->db->bind( ':mcr_member', $this->mcr_member );
		$this->db->bind( ':associate_member', $this->associate_member );
		$this->db->bind( ':cra', $this->cra );
		# Bind the User type values in correctly


		# Try to execute, print out error if there is one
		try {
			$this->db->execute();
		} catch ( \PDOException $e ) {
			echo $e->getMessage();
		}

		echo "<p>User " . $this->crsid . " CREATED.</p>\n";

		return true;
	}

	function delete() {
		# Removes the user from the system.

		$this->db->query( "DELETE FROM access WHERE id=:id AND crsid=:crsid" );
		$this->db->bind( ':id', $this->getValue( 'id' ) );
		$this->db->bind( ':crsid', $this->getValue( 'crsid' ) );
		try {
			$this->db->execute();
		} catch ( \PDOException $e ) {
			echo $e->getMessage();
		}

		echo "<p>User " . $this->getValue( 'crsid' ) . " DELETED.</p>\n";
	}

	function hasBooking( $eventid ) {

		# Checks whether the user has a booking for a given event.

		$this->db->query( "SELECT * FROM $this->bookingdetails WHERE booker=:crsid AND eventid=:eventid AND admin=0" );
		$this->db->bind( ':eventid', $eventid );
		$this->db->bind( ':crsid', $this->getValue( 'crsid' ) );
		$this->db->execute();

		if ( $this->db->rowCount() > 0 ) {
			return true;
		} else {
			return false;
		}
	}

	function hasPending( $eventid ) {

		# Checks whether the user has a pending booking

		$this->db->query( "SELECT * FROM $this->queue WHERE booker=:crsid AND eventid=:eventid AND admin=0" );
		$this->db->bind( ':eventid', $eventid );
		$this->db->bind( ':crsid', $this->crsid );
		$this->db->execute();

		if ( $this->db->rowCount() > 0 ) {
			return true;
		} else {
			return false;
		}
	}

	function hasAdminBooking( $eventid ) {

		# Checks whether there is an admin booking for a given event
		$this->db->query( "SELECT * FROM $this->$this->bookingdetails WHERE eventid=:eventid AND admin=1" );
		$this->db->bind( ':eventid', $eventid );
		$this->db->execute();

		if ( $this->db->rowCount() > 0 ) {
			return true;
		} else {
			return false;
		}
	}

	function hasAdminPending( $eventid ) {

		# Checks whether there is an admin booking pending for a given event
		$this->db->query( "SELECT * FROM $this->queue WHERE eventid=:eventid AND admin=1" );
		$this->db->bind( ':eventid', $eventid );
		$this->db->execute();

		if ( $this->db->rowCount() > 0 ) {
			return true;
		} else {
			return false;
		}
	}

}