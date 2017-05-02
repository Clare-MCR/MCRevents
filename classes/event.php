<?php
/**
 * Created by PhpStorm.
 * User: rg12
 * Date: 02/05/2017
 * Time: 13:24
 */

namespace claremcr\clareevents\classes;
use claremcr\clareevents\classes\genericitem;


class event extends genericitem {

	/* @class Event
	 * @abstract A class which describes all the information associated with
	 * an event, costs, timings, numbers of guests etc.
	 * @discussion Not much more to say really
	 */

	# Event associated variables
	public $id;
	public $name;
	public $event_date;
	public $open_date;
	public $close_date;
	public $max_guests;
	public $total_guests;
	public $current_guests;
	public $cost_normal;
	public $cost_second;
	public $guest_type;
	public $sent;

	public $mcr_member;
	public $cra;
	public $associate_member;
	private $eventslist;
	private $bookingdetails;
	private $bookings;
	private $queue;
	private $queuedetails;

	function __construct() {
		parent::__construct();
		$this->eventslist     = $this->my_pre . 'eventslist';
		$this->bookingdetails = $this->my_pre . 'booking_details';
		$this->bookings       = $this->my_pre . 'booking';
		$this->queue          = $this->my_pre . 'queue';
		$this->queuedetails   = $this->my_pre . 'queue_details';
	}

	function getEventFromID( $id ) {
		# Function to get event variables from a given id

		# Check event exists
		if ( ! $this->exists( $id ) ) {
			trigger_error( "Event does not exist.", E_USER_ERROR );
		}

		# Prepare the statement and execute
		$this->db->query( "SELECT * FROM $this->eventslist WHERE id=:eventid" );
		$this->db->bind( ":eventid", $id );

		# Set the fetch mode to pull the variables into this instance
		$results = $this->db->resultset();
		extract( $results );

	}

	function exists( $id ) {

		$this->db->query( "SELECT * FROM $this->eventslist WHERE id=:eventid" );
		$this->db->bind( ':eventid', $id );
		$this->db->execute();

		if ( $this->db->rowCount() != 1 ) {
			return false;
		} else {
			return true;
		}
	}

	function displayShortEvent() {
		# Prints out a shorter version of the event details
		echo "<div id=\"event_details\">\n";
		echo "<h2>" . $this->getValue( 'name' ) . "</h2>";
		echo "<p>Total Guests: " . $this->total_guests . "<p>";
		echo "<p>Current Guests:" . $this->current_guests . "</p>";
		echo "</div>";
	}

	function displayEvent() {
		# Function to print out the event details
		echo "<h3 class=\"event_name\">" . $this->name . "<span class=\"date\"> " . date( 'd/m/Y', strtotime( $this->event_date ) ) . "</span></h3>";
		echo "<table class=\"event_table\" border=\"0\">";
		echo "<tr><th>Event Date</th><th>Event Time</th><th>Booking Opens</th><th>Ticket Price</th><th>First Guest</th><th>Tickets Booked</th></tr>";
		echo "<tr><td>" . date( 'd/m/Y', strtotime( $this->event_date ) ) . "</td>";
		echo "<td>" . date( 'H:i', strtotime( $this->event_date ) ) . "</td>";
		echo "<td>" . date( 'd/m/Y - H:i', strtotime( $this->open_date ) ) . "</td>";
		echo "<td>&pound; " . $this->getValue( 'cost_normal' ) . "</td>";
		echo "<td>&pound; " . $this->getValue( 'cost_second' ) . "</td>";

		# Give 'FULL' message if the event is booked out.
		if ( $this->current_guests < $this->total_guests ) {

			echo "<td>" . $this->current_guests . "/" . $this->total_guests . " Guests</td></tr>";
		} else {
			echo "<td class=\"event_full\">FULL</td>";
		}
		echo "</table>";
	}

	function displayBookingControls( user &$user, $admin ) {

		# Prints a quick table with options to book the given event.

		echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">\n";
		echo "<input type=\"hidden\" name=\"eventid\" value=\"" . $this->getValue( 'id' ) . "\">\n";

		# If this is an admin display, make sure we have the admin flag
		if ( $admin == 1 ) {
			echo "<input type=\"hidden\" name=\"admin\" value=\"YES\">";
		}

		echo "<table border=\"0\" class=\"booking_controls\" >\n";

		# If this is admin, always print the book button.
		if ( $admin == 1 ) {
			echo "<tr><td><input type=\"submit\" name=\"event_select\" value=\"Make Admin Booking\"></td>\n";
		}

		# Only allow booking if the event hasnt closed, and the user hasn't already booked max_tickets (non admin)
		if ( strtotime( $this->getValue( 'close_date' ) ) > time() ) {
			if ( $admin != 1 ) {
				if ( tickets_ordered( $this->id, $user->GetValue( 'crsid' ) ) < max_tickets( $this->id ) ) {
					echo "<tr><td><input type=\"submit\" name=\"event_select\" value=\"Book Event\"></td>\n";
				}
			}
		}

		# Give the option to cancel a booking (non admin)
		if ( $admin != 1 ) {
			if ( $this->getValue( 'sent' ) == 'N' ) {
				if ( $user->hasBooking( $this->getValue( 'id' ) ) ) {
					echo "<td><input type=\"submit\" name=\"editbooking\" value=\"Edit Booking\"></td>";
				}
			}
		}

		# And edit a pending booking if appropriate (non admin), only if the event hasn't been sent.
		if ( $admin != 1 ) {
			if ( $this->getValue( 'sent' ) == 'N' ) {
				if ( $user->hasPending( $this->getValue( 'id' ) ) ) {
					echo "<td><input type=\"submit\" name=\"editpending\" value=\"Edit Pending\"></td>";
				}
			}
		}

		# And the same cancel controls for admins
		if ( $admin == 1 ) {
			if ( $this->getValue( 'sent' ) == 'N' ) {
				if ( $user->hasAdminBooking( $this->getValue( 'id' ) ) ) {
					echo "<td><input type=\"submit\" name=\"editbooking\" value=\"Edit Admin Booking\"></td>";
				}
				if ( $user->hasAdminPending( $this->getValue( 'id' ) ) ) {
					echo "<td><input type=\"submit\" name=\"editpending\" value=\"Edit Admin Pending\"></td>";
				}
			}
		}

		# Show the guest list if there are any guests for an event
		if ( $this->has_guests() == true ) {
			echo "<td><input type=\"submit\" name=\"guestlist\" value=\"See Guest List\"></td>\n";
		}

		echo "</tr></table>\n";
		echo "</form>\n";
	}

	function has_guests() {
		# Returns true/false dependent on whether a given event has any guests

		$this->db->query( "SELECT COUNT(*) FROM $this->bookingdetails WHERE eventid=:eventid" );
		$this->db->bind( ":eventid", $this->getValue( 'id' ) );


		$result = $this->db->resultset();

		if ( $result['COUNT(*)'] > 0 ) {
			return true;
		} else {
			return false;
		}
	}

	function create() {
		# Creates a new event using the provided variables

		$this->db->query( "INSERT INTO $this->eventslist (name,total_guests,current_guests,max_guests,mcr_member,associate_member,cra,non_clare_associate_member,cost_normal,cost_second,guest_type,event_date,open_date,close_date,sent) VALUES (:name,:total_guests,:current_guests,:max_guests,:mcr_member,:associate_member,:cra,0,:cost_normal,:cost_second,NULL,:event_date,:open_date,:close_date,:sent)" );
		$this->db->bind( ':name', $this->getValue( 'name' ) );
		$this->db->bind( ':total_guests', $this->getValue( 'total_guests' ) );
		$this->db->bind( ':current_guests', $this->getValue( 'current_guests' ) );
		$this->db->bind( ':max_guests', $this->getValue( 'max_guests' ) );
		$this->db->bind( ':cost_normal', $this->getValue( 'cost_normal' ) );
		$this->db->bind( ':cost_second', $this->getValue( 'cost_second' ) );
		$this->db->bind( ':event_date', $this->getValue( 'event_date' ) );
		$this->db->bind( ':open_date', $this->getValue( 'open_date' ) );
		$this->db->bind( ':close_date', $this->getValue( 'close_date' ) );
		$this->db->bind( ':sent', $this->getValue( 'sent' ) );

		# Bind the access types

		if ( $this->getValue( 'mcr_member' ) == 1 ) {
			$this->db->bind( ':mcr_member', $this->getValue( 'mcr_member' ) );
		} else {
			$this->db->bind( ':mcr_member', 0 );
		}

		if ( $this->getValue( 'associate_member' ) == 1 ) {
			$this->db->bind( ':associate_member', $this->getValue( 'associate_member' ) );
		} else {
			$this->db->bind( ':associate_member', 0 );
		}

		if ( $this->getValue( 'cra' ) == 1 ) {
			$this->db->bind( ':cra', $this->getValue( 'cra' ) );
		} else {
			$this->db->bind( ':cra', 0 );
		}

		$this->db->execute();

	}

	function commit() {
		# Commits the event to the database

		$this->db->query( "UPDATE $this->$this->eventslist SET name=:name,
 					total_guests=:total_guests, current_guests=:current_guests, max_guests=:max_guests,
 					mcr_member=:mcr_member, associate_member=:associate_member, cra=:cra, non_clare_associate_member=0,
 					cost_normal=:cost_normal, cost_second=:cost_second, guest_type=NULL,
 					event_date=:event_date, open_date=:open_date,
 					close_date=:close_date, sent=:sent WHERE id=:id " );

		# Bind the access types
		$this->db->bind( ':id', $this->id );
		$this->db->bind( ':name', $this->name );
		$this->db->bind( ':total_guests', $this->total_guests );
		$this->db->bind( ':current_guests', $this->current_guests );
		$this->db->bind( ':max_guests', $this->max_guests );
		$this->db->bind( ':cost_normal', $this->cost_normal );
		$this->db->bind( ':cost_second', $this->cost_second );
		$this->db->bind( ':event_date', $this->event_date );
		$this->db->bind( ':open_date', $this->open_date );
		$this->db->bind( ':close_date', $this->close_date );
		$this->db->bind( ':sent', $this->sent );

		if ( $this->mcr_member == 1 ) {
			$this->db->bind( ':mcr_member', $this->mcr_member );
		} else {
			$this->db->bind( ':mcr_member', 0 );
		}

		if ( $this->associate_member == 1 ) {
			$this->db->bind( ':associate_member', $this->associate_member );
		} else {
			$this->db->bind( ':associate_member', 0 );
		}

		if ( $this->cra == 1 ) {
			$this->db->bind( ':cra', $this->cra );
		} else {
			$this->db->bind( ':cra', 0 );
		}

		$this->db->execute();


	}

	function delete() {
		# Deletes an event from the database
	}

}