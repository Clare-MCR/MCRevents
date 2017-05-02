<?php
namespace clareevents\classes;

/**
 * MCR Events Booker class libraries
 *
 * This contains the class libraries defined for the booking system.
 *
 * @author James Clemence <james@jvc26.org>
 *
 */







abstract class Ticket extends genericitem {

	/*
	 * @class Ticket
	 * @abstract A class which describes any ticket, and is subclassed to dictate what type it becomes
	 * @discussion Ticket contains the variable $table, which must contain the table into which
	 * data is placed or retrieved from when the methods are called.
	 */

	# Variables

	public $id;
	public $bookingid;
	public $eventid;
	public $booker;
	public $admin;
	public $type;
	public $name;
	public $diet;
	public $other;
	public $table;
	public $classtype;

	function __construct( $tablename, $classtype, $givenid = null ) {
		parent::__construct();
		$this->table     = $tablename;
		$this->classtype = $classtype;

		# If we've been provided with an id in the instantiation call, get the rest of the info from
		# the database

		if ( ! is_null( $givenid ) ) {
			$this->getFromID( $givenid );
		}
	}

	function getFromID( $id ) {

		# Populates the tickets variables from the db

		global $my_pre;


		# Prepare the statement and execute
		$this->db->query( "SELECT * FROM " . $my_pre . $this->getValue( 'table' ) . " WHERE id=:ticketid" );
		$this->db->bind( ":ticketid", $id );

		if ( $this->exists( $id ) ) {
			$results = $this->db->resultset();
			extract( $results );
		} else {
			trigger_error( "The ticket requested does not appear to exist.", E_USER_ERROR );
		}
	}

	function exists( $id ) {

		$this->db->query( "SELECT * FROM " . $this->my_pre . $this->table . " WHERE id=:ticketid" );
		$this->db->bind( ':ticketid', $id );

		$this->db->execute();

		if ( ! $this->db->rowCount() >= 1 ) {
			return false;
		} else {
			return true;
		}
	}

	function create() {

	}

	function delete() {
		# Deletes a given ticket object from the database.

		$this->db->query( "DELETE FROM " . $this->my_pre . $this->getValue( 'table' ) . " WHERE id=:ticketid" );
		$this->db->bind( ":ticketid", $this->getValue( 'id' ) );
		$this->db->execute();

		# Let the user know what we've done
		echo "<p>Ticket for " . $this->name . " removed.</p>";
	}

	function commit() {
		# Commits given ticket changes to the database. Note this will only change $name $diet and $other.

		global $my_pre;


		$this->db->query( "UPDATE " . $my_pre . $this->getValue( 'table' ) . " SET name=:name, diet=:diet, other=:other WHERE id=:ticketid" );
		$this->db->bind( ":name", $this->name );
		$this->db->bind( ":diet", $this->diet );
		$this->db->bind( ":other", $this->other );
		$this->db->bind( ":ticketid", $this->id );

		$this->db->execute();

	}
}

class Application_Ticket extends Ticket {

	/* @class Application_Ticket
	 * @abstract This is a ticket which is associated with an application
	 * @discussion This is a subclass of ticket to set its $table variable correctly
	 */

	function __construct() {
		parent::__construct( 'queue_details', 'application' );
	}
}


class Booking_Ticket extends Ticket {

	/* @class Booking_Ticket
	 * @abstract This is a ticket which is associated with a Booking
	 * @discussion Subclasses ticket, but has a constructor to set the $table variable correctly
	 */

	function __construct() {
		parent::__construct( 'booking_details', 'booking' );
	}

}

abstract class Ticket_Parent extends genericItem {

	/*
	 * @class Ticket_Parent
	 * @abstract A class which describes the functions for Applications and Bookings
	 * @discussion This must be subclassed to set the $table variable in order to have the queries work properly.
	 */

	# Generic variables

	public $id;
	public $booker;
	public $eventid;
	public $admin;
	public $tickets;
	public $ticket_objects;
	public $table;
	public $classtype;

	public function __construct( $tablename, $classtypename ) {
		parent::__construct();
		$this->setValue( 'table', $tablename );
		$this->setValue( 'classtype', $classtypename );
	}

	function getFromID( $id ) {
		global $my_pre;


		$this->db->query( "SELECT COUNT(*) FROM " . $my_pre . $this->table . " WHERE id=:id" );
		$this->db->bind( ":id", $id );

		$result = $this->db->resultset();

		if ( ! $result['COUNT(*)'] == 1 ) {
			echo "The booking you referenced does not appear to exist.";

			return;
		}

		$this->db->query( "SELECT * FROM " . $my_pre . $this->table . " WHERE id=:id" );
		$this->db->bind( ":id", $id );
		extract( $this->db->single() );

	}

	function getTickets() {
		# Returns an array of ticket objects for the associated tickets.

		$ticketlist = $this->getValue( 'ticket_objects' );

		$this->db->query( "SELECT id FROM " . $this->my_pre . $this->table . "_details WHERE bookingid=:bookingid" );
		$this->db->bind( ":bookingid", $this->getValue( 'id' ) );


		$result = $this->db->resultset();

		if ( $this->db->rowCount() == 0 ) {
			trigger_error( "No tickets appear associated with this booking.", E_USER_ERROR );
		}


		foreach ( $result as $booking ) {
			if ( $this->classtype == 'application' ) {
				$ticket = new Application_Ticket;
			} else {
				$ticket = new Booking_Ticket;
			}

			$ticket->getFromID( $booking['id'] );

			$ticketlist[] = $ticket;
		}

		$this->setValue( 'ticket_objects', $ticketlist );
	}

	function create() {
	}

	function commit() {
		# Commit currently held information to database (note this only allows a change in #tickets)


		$this->db->query( "UPDATE " . $this->my_pre . $this->table . " SET tickets=:tickets WHERE id=:id" );
		$this->db->bind( ":tickets", $this->tickets );
		$this->db->bind( ":id", $this->id );

		$this->db->execute();

	}

	function delete() {
		# Delete the Ticket_Parent


		$event = new Event();
		$event->getEventFromID( $this->eventid );

		$this->db->query( "DELETE FROM " . $this->my_pre . $this->table . " WHERE id=:id" );
		$this->db->bind( ":id", $this->id );
		$this->db->execute();

		echo "<p>Booking #" . $this->id . " for event " . $event->name . " cancelled.</p>";

	}

	function displayApplication() {
	}

	function displayEdit() {
		$event = new Event;
		$event->getEventFromID( $this->eventid );

		# Modify header if we're a booking or an application
		echo "<h3 class=\"app_book_number\">";
		if ( $this->getValue( 'classtype' ) == 'application' ) {
			echo "Application";
		} else {
			echo "Booking";
		}
		echo " #" . $this->id . "</h3>";

		$tickets = $this->ticket_objects;

		# Form around table so we don't ire the system. LOL! (MS)
		echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
		echo "<table class=\"edit_booking\">\n";
		echo "<tr>";

		# Head the table differently for admins
		if ( $this->admin != 1 ) {
			echo "<th>Type</th><th>Name</th><th>Diet</th><th>Other</th><th>Delete</th></tr>\n";
		} else {
			echo "<th>Booker</th><th>Diet</th><th>Other</th><th>Delete</th></tr>\n";
		}

		for ( $i = 0; $i < count( $this->ticket_objects ); $i ++ ) {
			$ticket = $tickets[ $i ];

			# Cheeky hidden stuff for the form handling
			echo "<input type=\"hidden\" name=\"ticketarray[" . $i . "][id]\" value=\"" . $ticket->id . "\">";
			echo "<input type=\"hidden\" name=\"ticketarray[" . $i . "][bookingid]\" value=\"" . $ticket->bookingid . "\">";
			echo "<input type=\"hidden\" name=\"ticketarray[" . $i . "][eventid]\" value=\"" . $ticket->eventid . "\">";
			echo "<tr><td>";

			# Nice ticket type print (if not admin)

			if ( $ticket->admin != 1 ) {
				if ( $ticket->type == 1 ) {
					echo "PRIMARY</td>";
				} else {
					echo "&gt;&gt; Sub</td>";
				}
			}

			# If we are an admin, do the tidy Booker printing

			if ( $ticket->admin == 1 ) {
				echo $ticket->booker . "</td>";
			}

			# If it's an admin ticket, you can't alter the name
			if ( $ticket->admin != 1 ) {
				echo "<td><input type=\"text\" name=\"ticketarray[" . $i . "][name]\" value=\"" . $ticket->name . "\"></td>";
			} else {
				echo "<input type=\"hidden\" name=\"ticketarray[" . $i . "][name]\" value=\"" . $ticket->name . "\">";
			}

			# Print out the form for changing diet
			echo "<td><input type=\"radio\" name=\"ticketarray[" . $i . "][diet]\" id=\"nodiet_" . $i . "\" value=\"None\"";
			if ( $ticket->diet == 'None' ) {
				echo "checked";
			}
			echo "><label for=\"nodiet_" . $i . "\">N/A</label>";
			echo "<input type=\"radio\" name=\"ticketarray[" . $i . "][diet]\" id=\"vgtdiet_" . $i . "\" value=\"Vegetarian\"";
			if ( $ticket->diet == 'Vegetarian' ) {
				echo "checked";
			}
			echo "><label for=\"vgtdiet_" . $i . "\" title=\"Vegetarian\">Vgt</label>";
			echo "<input type=\"radio\" name=\"ticketarray[" . $i . "][diet]\" id=\"vgndiet_" . $i . "\" value=\"Vegan\"";
			if ( $ticket->diet == 'Vegan' ) {
				echo "checked";
			}
			echo "><label for=\"vgndiet_" . $i . "\" title=\"Vegan\">Vgn</label>";
			echo "<td><input type=\"text\" name=\"ticketarray[" . $i . "][other]\" value=\"" . $ticket->other . "\" size=\"10\"></td>";

			# And a checkbox to delete the whole thing if you don't want to go
			echo "<td><input type=\"checkbox\" name=\"ticketarray[" . $i . "][delete]\" value=\"\"></td>";
		}

		# Submission and closure
		if ( $this->getValue( 'classtype' ) == 'application' ) {
			echo "<td><input type=\"submit\" name=\"editpending\" value=\"Submit\"></td></tr>";
		} else {
			echo "<td><input type=\"submit\" name=\"editbooking\" value=\"Submit\"></td></tr>";
		}
		echo "</table>\n";
		echo "</form>";
	}


}

class Application extends Ticket_Parent {

	/* @class Application
	 * @abstract Extends Ticket_Parent to provide a reference for the details associated with a ticket application
	 * @discussion Just a subclass in order to set the $table var correctly
	 */

	function __construct() {
		parent::__construct( 'queue', 'application' );
	}
}

class Booking extends Ticket_Parent {

	/* @class Booking
	 * @abstract A Booking reference, containing #tickets, booker, and event
	 * @discussion This extends the Ticket_Parent class to set its $table var correctly
	 */

	function __construct() {
		parent::__construct( 'booking', 'booking' );
	}
}

class Validator {

	/* @class Validator
	 * @abstract Handles validation of input.
	 * @description Can be called as static methods, checks input
	 * and if invalid triggers an error The methods check the
	 * input and then output an error message which can also provide
	 * the name of the variable which caused the error.
	 *
	 * @param $input
	 * @param null $varstring
	 */

	static public function isAlpha( $input, $varstring = null ) {
		if ( ! preg_match( '/^[\w\s]+$/', $input ) ) {
			trigger_error( "Input value is not alphanumeric", E_USER_ERROR );
		}
	}

	static public function isNumeric( $input, $varstring = null ) {
		if ( ! preg_match( '/^[0-9\.]+$/', $input ) ) {
			trigger_error( "Input value is not a number", E_USER_ERROR );
		}
	}

	static public function isName( $input, $varstring = null ) {
		return;
	}

	static public function isDate( $input, $varstring = null ) {
		if ( ! preg_match( "/^[0-9]{2}-[0-9]{2}-[0-9]{2}$/", $input ) ) {
			trigger_error( "Date provided is not a valid value", E_USER_ERROR );
		}
	}

	static public function isTime( $input, $varstring = null ) {
		if ( ! preg_match( "/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/", $input ) ) {
			trigger_error( "Time provided is not a valid value", E_USER_ERROR );
		}
	}
}

?>
