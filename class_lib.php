<?

/**
 * MCR Events Booker class libraries
 *
 * This contains the class libraries defined for the booking system.
 *
 * @author James Clemence <james@jvc26.org>
 *
 */


/** @class genericItem
 *  @abstract Contains simply getter and setter functions
 *  @description A superclass to provide getter and setter functions
 *  for subclasses
 */
class genericItem {

    /**
     * getValue($val)
     * Returns the value of the requested property.i
     *
     * @param string $val
     * @return string $val
     */
    function getValue($val) {
        return $this->$val;
    }

    /**
     * setValue sets a property given a value and a varibale given in
     * the arguments.
     *
     * @param string $val
     * @param string $value
     */
    function setValue($val, $value) {
        $this->$val = $value;
    }
}


/**
 * User class
 *
 * @abstract The User class, controls access, user permissions and stores
 * the user's crsid
 * @description Not much more to say than the abstract really
 */
class User extends genericItem {

    # User associated variables
    public $id;
    public $crsid;
    public $e_view;
    public $e_book;
    public $e_adm;
    public $s_adm;
    public $p_view;
    public $p_book;
    public $p_adm;
    public $type;
    public $enabled;
    public $name;

    /**
     * has_perm function
     * @abstract Checks whether user has permission to perform an action
     * @param string $perm
     * @return bool True/False
     */
    function has_perm($perm) {
        if ($this->getValue($perm) != 1) {
            return False;
        } else {
            return True;
        }
    }

    /**
     * exists function
     * @abstract Checks whether user exists by checking crsid against the db
     * @global $dbh
     * @param string $crsid
     * @return bool True/False
     */
    function exists($crsid) {

        global $dbh;

        $statement = $dbh->prepare("SELECT COUNT(*) FROM access WHERE crsid=:crsid");
        $statement->bindValue(":crsid", $crsid);
        $statement->execute();
        $result = $statement->fetch();

        if ($result[0] == 1) {
            return True;
        } else {
            return False;
        }
    }

    # Grabs all the vars from the database of an existing user
    function getFromCRSID($crsid) {

        global $dbh;

        # See whether user exists
        if (!$this->exists($crsid)) {
            echo "<p>User ".$crsid." does not exist.</p>\n";
            return False;
        }

        # Prepare the statement and execute
        $statement = $dbh->prepare("SELECT * FROM access WHERE crsid=:crsid");
        $statement->bindValue(":crsid", $crsid);
        $statement->execute();

        # Set the fetch mode to pull the variables into this instance
        $statement->setFetchMode(PDO::FETCH_INTO, $this);
        $statement->fetch();

        # Get the username from LDAP
        # ldap lookup the user's name, and if we can, put their name into the name field

        $ds = ldap_connect("ldap.lookup.cam.ac.uk");
        $lsearch = ldap_search($ds, "ou=people,o=University of Cambridge,dc=cam,dc=ac,dc=uk", "uid=" . $this->getValue('crsid'). "");
        $info = ldap_get_entries($ds, $lsearch);
        $name = $info[0]["cn"][0];

        if ($name == "") {
            $this->setValue('name', $this->getValue('crsid'));
        } else {
            $this->setValue('name', $name);
        }

        return True;
    }

    # Commits the user information to the database.
    # Note this cannot change s_adm status, only events related stuff.
    function commit_events_user() {

        global $dbh;

        $statement = $dbh->prepare("UPDATE access SET e_view=:e_view, e_book=:e_book, e_adm=:e_adm, mcr_member=:mcr_member, 4th_year=:4th_year, cra=:cra, enabled=:enabled WHERE id=:id");
        $statement->bindValue(':e_view',$this->getValue('e_view'));
        $statement->bindValue(':e_book',$this->getValue('e_book'));
        $statement->bindValue(':e_adm',$this->getValue('e_adm'));
        $statement->bindValue(':enabled',$this->getValue('enabled'));
        $statement->bindValue(':id',$this->getValue('id'));

        # Bind the values related to access perms correctly

        if ($this->getValue('mcr_member') == 1) {
            $statement->bindValue(':mcr_member',$this->getValue('mcr_member'));
        } else {
            $statement->bindValue(':mcr_member',0);
        }

        if ($this->getValue('4th_year') == 1) {
            $statement->bindValue(':4th_year',$this->getValue('4th_year'));
        } else {
            $statement->bindValue(':4th_year',0);
        }

        if ($this->getValue('cra') == 1) {
            $statement->bindValue(':cra',$this->getValue('cra'));
        } else {
            $statement->bindValue(':cra',0);
        }


        try {
            $statement->execute();
        }
        catch(PDOException $e) {
            echo $e->getMessage();
        }
    }

    function commit_punts_user() {

        global $dbh;

        $statement = $dbh->prepare("UPDATE access SET p_view=:p_view, p_book=:p_book, p_adm=:p_adm, enabled=:enabled WHERE id=:id");
        $statement->bindValue(':p_view',$this->getValue('p_view'));
        $statement->bindValue(':p_book',$this->getValue('p_book'));
        $statement->bindValue(':p_adm',$this->getValue('p_adm'));
        $statement->bindValue(':enabled',$this->getValue('enabled'));
        $statement->bindValue(':id',$this->getValue('id'));

        try {
            $statement->execute();
        }
        catch(PDOException $e) {
            echo $e->getMessage();
        }
    }

    function create_punts_user() {

        global $dbh;

        # Creates a user, with the perms set as per user input

        # Check that user doesnt exist:
        if ($this->exists($this->getValue('crsid'))) {
            echo "<p>User " . $this->getValue('crsid') . " EXISTS</p>\n";
            return False;
        }


        $statement = $dbh->prepare("INSERT INTO access (crsid,p_view,p_book,p_adm,type,s_adm,enabled) VALUES (:crsid,:p_view,:p_book,:p_adm,:type,:s_adm,:enabled)");
        $statement->bindValue(':crsid',$this->getValue('crsid'));
        $statement->bindValue(':p_view',$this->getValue('p_view'));
        $statement->bindValue(':p_book',$this->getValue('p_book'));
        $statement->bindValue(':p_adm',$this->getValue('p_adm'));
        $statement->bindValue(':s_adm','0');
        $statement->bindValue(':type','1');
        $statement->bindValue(':enabled',$this->getValue('enabled'));

        try {
            $statement->execute();
        }
        catch(PDOException $e) {
            echo $e->getMessage();
        }

        echo "<p>User " . $this->getValue('crsid') . " CREATED.</p>\n";
    }

    # Creates a new user and commits them to the database.
    function create_user() {

        global $dbh;

        # Creates a user, with the perms set as per user input

        # Check that user doesnt exist:
        if ($this->exists($this->getValue('crsid'))) {
            echo "<p>User " . $this->getValue('crsid') . " EXISTS</p>\n";
            return False;
        }

        # Prepare the statement and bind the given values
        $statement = $dbh->prepare("INSERT INTO access (crsid,e_view,e_book,e_adm,mcr_member,4th_year,cra,s_adm,enabled) VALUES (:crsid,:e_view,:e_book,:e_adm,:mcr_member,:4th_year,:cra,:s_adm,:enabled)");
        $statement->bindValue(':crsid',$this->getValue('crsid'));
        $statement->bindValue(':e_view',$this->getValue('e_view'));
        $statement->bindValue(':e_book',$this->getValue('e_book'));
        $statement->bindValue(':e_adm',$this->getValue('e_adm'));
        $statement->bindValue(':s_adm','0');
        $statement->bindValue(':enabled',$this->getValue('enabled'));

        # Bind the User type values in correctly

        if ($this->getValue('mcr_member') == 1) {
            $statement->bindValue(':mcr_member',$this->getValue('mcr_member'));
        } else {
            $statement->bindValue(':mcr_member',0);
        }

        if ($this->getValue('4th_year') == 1) {
            $statement->bindValue(':4th_year',$this->getValue('4th_year'));
        } else {
            $statement->bindValue(':4th_year',0);
        }

        if ($this->getValue('cra') == 1) {
            $statement->bindValue(':cra',$this->getValue('cra'));
        } else {
            $statement->bindValue(':cra',0);
        }


        # Try to execute, print out error if there is one
        try {
            $statement->execute();
        }
        catch(PDOException $e) {
            echo $e->getMessage();
        }

        echo "<p>User " . $this->getValue('crsid') . " CREATED.</p>\n";

    }

    function delete() {

        global $dbh;

        # Removes the user from the system.

        $statement = $dbh->prepare("DELETE FROM access WHERE id=:id AND crsid=:crsid");
        $statement->bindValue(':id',$this->getValue('id'));
        $statement->bindValue(':crsid',$this->getValue('crsid'));
        try {
            $statement->execute();
        }
        catch (PDOException $e) {
            echo $e->getMessage();
        }

        echo "<p>User " . $this->getValue('crsid') . " DELETED.</p>\n";
    }

    function hasBooking($eventid) {

        # Checks whether the user has a booking for a given event.

        global $my_pre;
        global $dbh;

        $statement = $dbh->prepare("SELECT * FROM " . $my_pre . "booking_details WHERE booker=:crsid AND eventid=:eventid AND admin=0");
        $statement->bindValue(':eventid', $eventid);
        $statement->bindValue(':crsid',$this->getValue('crsid'));
        $statement->execute();

        if ($statement->rowCount() > 0) {
            return True;
        } else {
            return False;
        }
    }

    function hasPending($eventid) {

        # Checks whether the user has a pending booking

        global $my_pre;
        global $dbh;

        $statement = $dbh->prepare("SELECT * FROM " . $my_pre . "queue WHERE booker=:crsid AND eventid=:eventid AND admin=0");
        $statement->bindValue(':eventid', $eventid);
        $statement->bindValue(':crsid',$this->getValue('crsid'));
        $statement->execute();

        if ($statement->rowCount() > 0) {
            return True;
        } else {
            return False;
        }
    }

    function hasAdminBooking($eventid) {

        # Checks whether there is an admin booking for a given event

        global $my_pre;
        global $dbh;

        $statement = $dbh->prepare("SELECT * FROM " . $my_pre . "booking_details WHERE eventid=:eventid AND admin=1");
        $statement->bindValue(':eventid', $eventid);
        $statement->execute();

        if ($statement->rowCount() > 0) {
            return True;
        } else {
            return False;
        }
    }

    function hasAdminPending($eventid) {

        # Checks whether there is an admin booking pending for a given event

        global $my_pre;
        global $dbh;

        $statement = $dbh->prepare("SELECT * FROM " . $my_pre . "queue WHERE eventid=:eventid AND admin=1");
        $statement->bindValue(':eventid', $eventid);
        $statement->execute();

        if ($statement->rowCount() > 0) {
            return True;
        } else {
            return False;
        }
    }

}

class Event extends genericItem {

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

    function exists($id) {

        global $my_pre;
        global $dbh;

        $statement = $dbh->prepare("SELECT * FROM " . $my_pre . "eventslist WHERE id=:eventid");
        $statement->bindValue(':eventid', $id);
        $statement->execute();

        if ($statement->rowCount() != 1) {
            return False;
        } else {
            return True;
        }
    }

    function getEventFromID($id) {
        # Function to get event variables from a given id
        global $my_pre;
        global $dbh;

        # Check event exists
        if (! $this->exists($id)) {
	    trigger_error("Event does not exist.", E_USER_ERROR);
        }

        # Prepare the statement and execute
        $statement = $dbh->prepare("SELECT * FROM " . $my_pre . "eventslist WHERE id=:eventid");
        $statement->bindValue(":eventid", $id);
        $statement->execute();

        # Set the fetch mode to pull the variables into this instance
        $statement->setFetchMode(PDO::FETCH_INTO, $this);
        $statement->fetch();

    }

    function displayShortEvent() {
        # Prints out a shorter version of the event details
        echo "<div id=\"event_details\">\n";
        echo "<h2>" . $this->getValue('name') . "</h2>";
        echo "<p>Total Guests: ".$this->getValue('total_guests') . "<p>";
        echo "<p>Current Guests:" . $this->getValue('current_guests') . "</p>";
        echo "</div>";
    }

    function displayEvent() {
        # Function to print out the event details
        echo "<h3 class=\"event_name\">" . $this->getValue('name') . "<span class=\"date\"> " . date('d/m/Y', strtotime($this->getValue('event_date'))) . "</span></h3>";
        echo "<table class=\"event_table\" border=\"0\">";
        echo "<tr><th>Event Date</th><th>Event Time</th><th>Booking Opens</th><th>Ticket Price</th><th>First Guest</th><th>Tickets Booked</th></tr>";
        echo "<tr><td>" . date('d/m/Y', strtotime($this->getValue('event_date'))) . "</td>";
        echo "<td>" . date('H:i', strtotime($this->getValue('event_date'))) . "</td>";
        echo "<td>" . date('d/m/Y - H:i', strtotime($this->getValue('open_date'))) . "</td>";
        echo "<td>&pound; " . $this->getValue('cost_normal') . "</td>";
        echo "<td>&pound; " . $this->getValue('cost_second') . "</td>";

        # Give 'FULL' message if the event is booked out.
        if ($this->getValue('current_guests') < $this->getValue('total_guests')) {

            echo "<td>" . $this->getValue('current_guests') . "/" . $this->getValue('total_guests') . " Guests</td></tr>";
        } else {
            echo "<td class=\"event_full\">FULL</td>";
        }
        echo "</table>";
    }

    function displayBookingControls($user, $admin) {

        # Prints a quick table with options to book the given event.

        echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">\n";
        echo "<input type=\"hidden\" name=\"eventid\" value=\"" . $this->getValue('id') . "\">\n";

        # If this is an admin display, make sure we have the admin flag
        if ($admin == 1) {
            echo "<input type=\"hidden\" name=\"admin\" value=\"YES\">";
        }

        echo "<table border=\"0\" class=\"booking_controls\" >\n";

        # If this is admin, always print the book button.
        if ($admin == 1) {
            echo "<tr><td><input type=\"submit\" name=\"event_select\" value=\"Make Admin Booking\"></td>\n";
        }

        # Only allow booking if the event hasnt closed, and the user hasn't already booked max_tickets (non admin)
        if (strtotime($this->getValue('close_date')) > time()) {
            if ($admin != 1) {
                if (tickets_ordered($this->getValue('id'), $user->getValue('crsid')) < max_tickets($this->getValue('id'))) {
                    echo "<tr><td><input type=\"submit\" name=\"event_select\" value=\"Book Event\"></td>\n";
                }
            }
        }

        # Give the option to cancel a booking (non admin)
        if ($admin != 1) {
            if ($this->getValue('sent') == 'N') {
                if ($user->hasBooking($this->getValue('id'))) {
                    echo "<td><input type=\"submit\" name=\"editbooking\" value=\"Edit Booking\"></td>";
                }
            }
        }

        # And edit a pending booking if appropriate (non admin), only if the event hasn't been sent.
        if ($admin != 1) {
            if ($this->getValue('sent') == 'N') {
                if ($user->hasPending($this->getValue('id'))) {
                    echo "<td><input type=\"submit\" name=\"editpending\" value=\"Edit Pending\"></td>";
                }
            }
        }

        # And the same cancel controls for admins
        if ($admin == 1) {
            if ($this->getValue('sent') == 'N') {
                if ($user->hasAdminBooking($this->getValue('id'))) {
                    echo "<td><input type=\"submit\" name=\"editbooking\" value=\"Edit Admin Booking\"></td>";
                }
                if ($user->hasAdminPending($this->getValue('id'))) {
                    echo "<td><input type=\"submit\" name=\"editpending\" value=\"Edit Admin Pending\"></td>";
                }
            }
        }

        # Show the guest list if there are any guests for an event
        if ($this->has_guests() == True) {
            echo "<td><input type=\"submit\" name=\"guestlist\" value=\"See Guest List\"></td>\n";
        }

        echo "</tr></table>\n";
        echo "</form>\n";
    }

    function create() {
        # Creates a new event using the provided variables

        global $my_pre;
        global $dbh;

        $statement = $dbh->prepare("INSERT INTO " . $my_pre . "eventslist (name,total_guests,current_guests,max_guests,mcr_member,associate_member,cra,non_clare_associate_member,cost_normal,cost_second,guest_type,event_date,open_date,close_date,sent) VALUES (:name,:total_guests,:current_guests,:max_guests,:mcr_member,:associate_member,:cra,0,:cost_normal,:cost_second,NULL,:event_date,:open_date,:close_date,:sent)");
        $statement->bindValue(':name',$this->getValue('name'));
        $statement->bindValue(':total_guests',$this->getValue('total_guests'));
        $statement->bindValue(':current_guests',$this->getValue('current_guests'));
        $statement->bindValue(':max_guests',$this->getValue('max_guests'));
        $statement->bindValue(':cost_normal',$this->getValue('cost_normal'));
        $statement->bindValue(':cost_second',$this->getValue('cost_second'));
        $statement->bindValue(':event_date',$this->getValue('event_date'));
        $statement->bindValue(':open_date',$this->getValue('open_date'));
        $statement->bindValue(':close_date',$this->getValue('close_date'));
        $statement->bindValue(':sent',$this->getValue('sent'));

        # Bind the access types

        if ($this->getValue('mcr_member') == 1) {
            $statement->bindValue(':mcr_member',$this->getValue('mcr_member'));
        } else {
            $statement->bindValue(':mcr_member',0);
        }

        if ($this->getValue('associate_member') == 1) {
            $statement->bindValue(':associate_member',$this->getValue('associate_member'));
        } else {
            $statement->bindValue(':associate_member',0);
        }

        if ($this->getValue('cra') == 1) {
            $statement->bindValue(':cra',$this->getValue('cra'));
        } else {
            $statement->bindValue(':cra',0);
        }

        $statement->execute();

    }

    function commit() {
        # Commits the event to the database
        global $my_pre;
        global $dbh;

 	$statement = $dbh->prepare('UPDATE '. $my_pre.'eventslist SET name=:name,
 					total_guests=:total_guests, current_guests=:current_guests, max_guests=:max_guests,
 					mcr_member=:mcr_member, associate_member=:associate_member, cra=:cra, non_clare_associate_member=0,
 					cost_normal=:cost_normal, cost_second=:cost_second, guest_type=NULL,
 					event_date=:event_date, open_date=:open_date,
 					close_date=:close_date, sent=:sent WHERE id=:id ');

	# Bind the access types
	$statement->bindValue(':id', $this->id);
        $statement->bindValue(':name',$this->name);
        $statement->bindValue(':total_guests',$this->total_guests);
        $statement->bindValue(':current_guests',$this->current_guests);
        $statement->bindValue(':max_guests',$this->max_guests);
        $statement->bindValue(':cost_normal',$this->cost_normal);
        $statement->bindValue(':cost_second',$this->cost_second);
        $statement->bindValue(':event_date',$this->event_date);
        $statement->bindValue(':open_date',$this->open_date);
        $statement->bindValue(':close_date',$this->close_date);
        $statement->bindValue(':sent',$this->sent);

        if ($this->mcr_member == 1) {
            $statement->bindValue(':mcr_member',$this->mcr_member);
        } else {
            $statement->bindValue(':mcr_member',0);
        }

        if ($this->getValue('associate_member') == 1) {
            $statement->bindValue(':associate_member',$this->getValue('associate_member'));
        } else {
            $statement->bindValue(':associate_member',0);
        }

        if ($this->cra == 1) {
            $statement->bindValue(':cra',$this->cra);
        } else {
            $statement->bindValue(':cra',0);
        }

        $statement->execute();


    }

    function delete() {
        # Deletes an event from the database
    }

    function has_guests() {
        # Returns true/false dependent on whether a given event has any guests

        global $dbh;
        global $my_pre;

        $statement = $dbh->prepare("SELECT COUNT(*) FROM " . $my_pre . "booking_details WHERE eventid=:eventid");
        $statement->bindValue(":eventid", $this->getValue('id'));
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if ($result['COUNT(*)'] > 0) {
            return True;
        } else {
            return False;
        }
    }

}

abstract class Ticket extends genericItem {

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

    function __construct($tablename, $classtype, $givenid = NULL) {
        $this->setValue('table', $tablename);
        $this->setValue('classtype', $classtype);

        # If we've been provided with an id in the instantiation call, get the rest of the info from
        # the database

        if (! is_null($givenid)) {
            $this->getFromID($givenid);
        }
    }

    function exists($id) {

        global $my_pre;
        global $dbh;

        $statement = $dbh->prepare("SELECT * FROM " . $my_pre . $this->getValue('table') . " WHERE id=:ticketid");
        $statement->bindValue(':ticketid', $id);
        $statement->execute();

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (! count($result) >= 1) {
            return False;
        } else {
            return True;
        }
    }

    function getFromID($id) {

        # Populates the tickets variables from the db

        global $my_pre;
        global $dbh;

        # Prepare the statement and execute
        $statement = $dbh->prepare("SELECT * FROM " . $my_pre . $this->getValue('table') . " WHERE id=:ticketid");
        $statement->bindValue(":ticketid", $id);
        $statement->execute();

        # Set the fetch mode to pull the variables into this instance
        $statement->setFetchMode(PDO::FETCH_INTO, $this);

        if ($this->exists($id)) {
            $statement->fetch();
        } else {
	    trigger_error("The ticket requested does not appear to exist.", E_USER_ERROR);
        }
    }

    function create() {

    }

    function delete() {
        # Deletes a given ticket object from the database.

        global $my_pre;
        global $dbh;

        $statement = $dbh->prepare("DELETE FROM " . $my_pre . $this->getValue('table') . " WHERE id=:ticketid");
        $statement->bindValue(":ticketid", $this->getValue('id'));
        $statement->execute();

        # Let the user know what we've done
        echo "<p>Ticket for " . $this->getValue('name') . " removed.</p>";
    }

    function commit() {
        # Commits given ticket changes to the database. Note this will only change $name $diet and $other.

        global $my_pre;
        global $dbh;

        $statement = $dbh->prepare("UPDATE " . $my_pre . $this->getValue('table') . " SET name=:name, diet=:diet, other=:other WHERE id=:ticketid");
        $statement->bindValue(":name", $this->getValue('name'));
        $statement->bindValue(":diet", $this->getValue('diet'));
        $statement->bindValue(":other", $this->getValue('other'));
        $statement->bindValue(":ticketid", $this->getValue('id'));

        $statement->execute();

    }
}

class Application_Ticket extends Ticket {

    /* @class Application_Ticket
     * @abstract This is a ticket which is associated with an application
     * @discussion This is a subclass of ticket to set its $table variable correctly
     */

    function __construct() {
        parent::__construct('queue_details', 'application');
    }
}


class Booking_Ticket extends Ticket {

    /* @class Booking_Ticket
     * @abstract This is a ticket which is associated with a Booking
     * @discussion Subclasses ticket, but has a constructor to set the $table variable correctly
     */

    function __construct() {
        parent::__construct('booking_details', 'booking');
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

    public function __construct($tablename, $classtypename) {
        $this->setValue('table',$tablename);
        $this->setValue('classtype',$classtypename);
    }

    function getFromID($id) {
        global $my_pre;
        global $dbh;

        $statement = $dbh->prepare("SELECT COUNT(*) FROM " . $my_pre . $this->getValue('table') . " WHERE id=:id");
        $statement->bindValue(":id", $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if (! $result['COUNT(*)'] ==1) {
            echo "The booking you referenced does not appear to exist.";
            return;
        }

        $statement = $dbh->prepare("SELECT * FROM " . $my_pre . $this->getValue('table') . " WHERE id=:id");
        $statement->bindValue(":id", $id);
        $statement->execute();

        $statement->setFetchMode(PDO::FETCH_INTO, $this);

        $statement->fetch();

    }

    function getTickets() {
        # Returns an array of ticket objects for the associated tickets.

        global $my_pre;
        global $dbh;

        $ticketlist = $this->getValue('ticket_objects');

        $statement = $dbh->prepare("SELECT id FROM " . $my_pre . $this->getValue('table') . "_details WHERE bookingid=:bookingid");
        $statement->bindValue(":bookingid", $this->getValue('id'));
        $statement->execute();

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (count($result) == 0) {
            trigger_error("No tickets appear associated with this booking.", E_USER_ERROR);
        }


        foreach ($result as $booking) {
            if ($this->getValue('classtype') == 'application') {
                $ticket = new Application_Ticket;
            } else {
                $ticket = new Booking_Ticket;
            }

            $ticket->getFromID($booking['id']);

            $ticketlist[] = $ticket;
        }

        $this->setValue('ticket_objects', $ticketlist);
    }

    function create() {
    }

    function commit() {
        # Commit currently held information to database (note this only allows a change in #tickets)

        global $my_pre;
        global $dbh;

        $statement = $dbh->prepare("UPDATE " .  $my_pre . $this->getValue('table') . " SET tickets=:tickets WHERE id=:id");
        $statement->bindValue(":tickets", $this->getValue('tickets'));
        $statement->bindValue(":id", $this->getValue('id'));

        $statement->execute();

    }

    function delete() {
        # Delete the Ticket_Parent

        global $my_pre;
        global $dbh;

        $event = new Event();
        $event->getEventFromID($this->getValue('eventid'));

        $statement = $dbh->prepare("DELETE FROM " . $my_pre . $this->getValue('table') . " WHERE id=:id");
        $statement->bindValue(":id", $this->getValue('id'));
        $statement->execute();

        echo "<p>Booking #" . $this->getValue('id') . " for event " . $event->getValue('name') . " cancelled.</p>";

    }

    function displayApplication() {
    }

    function displayEdit() {
        $event = new Event;
        $event->getEventFromID($this->getValue('eventid'));

        # Modify header if we're a booking or an application
        echo "<h3 class=\"app_book_number\">";
        if ($this->getValue('classtype') == 'application') {
            echo "Application";
        } else {
            echo "Booking";
        }
        echo " #" . $this->getValue('id') . "</h3>";

        $tickets = $this->getValue('ticket_objects');

        # Form around table so we don't ire the system. LOL! (MS)
        echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
        echo "<table class=\"edit_booking\">\n";
        echo "<tr>";

        # Head the table differently for admins
        if ($this->getValue('admin') != 1) {
            echo "<th>Type</th><th>Name</th><th>Diet</th><th>Other</th><th>Delete</th></tr>\n";
        } else {
            echo "<th>Booker</th><th>Diet</th><th>Other</th><th>Delete</th></tr>\n";
        }

        for ($i = 0; $i < count($this->getValue('ticket_objects')); $i++) {
            $ticket = $tickets[$i];

            # Cheeky hidden stuff for the form handling
            echo "<input type=\"hidden\" name=\"ticketarray[" . $i . "][id]\" value=\"" . $ticket->getValue('id') . "\">";
            echo "<input type=\"hidden\" name=\"ticketarray[" . $i . "][bookingid]\" value=\"" . $ticket->getValue('bookingid') . "\">";
            echo "<input type=\"hidden\" name=\"ticketarray[" . $i . "][eventid]\" value=\"" . $ticket->getValue('eventid') . "\">";
            echo "<tr><td>";

            # Nice ticket type print (if not admin)

            if ($ticket->getValue('admin') != 1) {
                if ($ticket->getValue('type') == 1) {
                    echo "PRIMARY</td>";
                } else {
                    echo "&gt;&gt; Sub</td>";
                }
            }

            # If we are an admin, do the tidy Booker printing

            if ($ticket->getValue('admin') == 1) {
                echo $ticket->getValue('booker') . "</td>";
            }

            # If it's an admin ticket, you can't alter the name
            if ($ticket->getValue('admin') != 1) {
                echo "<td><input type=\"text\" name=\"ticketarray[" . $i . "][name]\" value=\"" . $ticket->getValue('name') . "\"></td>";
            } else {
                echo "<input type=\"hidden\" name=\"ticketarray[" . $i . "][name]\" value=\"" . $ticket->getValue('name') . "\">";
            }

            # Print out the form for changing diet
            echo "<td><input type=\"radio\" name=\"ticketarray[" . $i . "][diet]\" id=\"nodiet_" . $i . "\" value=\"None\"";
            if ($ticket->getValue('diet') == 'None') { echo "checked"; }
            echo "><label for=\"nodiet_" . $i . "\">N/A</label>";
            echo "<input type=\"radio\" name=\"ticketarray[" . $i . "][diet]\" id=\"vgtdiet_" . $i . "\" value=\"Vegetarian\"";
            if ($ticket->getValue('diet') == 'Vegetarian') { echo "checked"; }
            echo "><label for=\"vgtdiet_" . $i . "\" title=\"Vegetarian\">Vgt</label>";
            echo "<input type=\"radio\" name=\"ticketarray[" . $i . "][diet]\" id=\"vgndiet_" . $i . "\" value=\"Vegan\"";
            if ($ticket->getValue('diet') == 'Vegan') { echo "checked"; }
            echo "><label for=\"vgndiet_" . $i . "\" title=\"Vegan\">Vgn</label>";
            echo "<td><input type=\"text\" name=\"ticketarray[" . $i . "][other]\" value=\"" . $ticket->getValue('other') . "\" size=\"10\"></td>";

            # And a checkbox to delete the whole thing if you don't want to go
            echo "<td><input type=\"checkbox\" name=\"ticketarray[" . $i . "][delete]\" value=\"\"></td>";
        }

        # Submission and closure
        if ($this->getValue('classtype') == 'application') {
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
        parent::__construct('queue','application');
    }
}

class Booking extends Ticket_Parent {

    /* @class Booking
     * @abstract A Booking reference, containing #tickets, booker, and event
     * @discussion This extends the Ticket_Parent class to set its $table var correctly
     */

    function __construct() {
        parent::__construct('booking','booking');
    }
}

class Validator {

    /* @class Validator
    * @abstract Handles validation of input.
    * @description Can be called as static methods, checks input
    * and if invalid triggers an error The methods check the
    * input and then output an error message which can also provide
    * the name of the variable which caused the error.
    */

    static public function isAlpha($input, $varstring=null) {
        if (! preg_match("/^[\w\s]+$/", $input)) {
            trigger_error("Input value is not alphanumeric", E_USER_ERROR);
            }
        }

    static public function isNumeric($input, $varstring=null) {
        if (! preg_match("/^[0-9\.]+$/", $input)) {
            trigger_error("Input value is not a number", E_USER_ERROR);
        }
    }

    static public function isName($input, $varstring=null) {
        continue;
    }

    static public function isDate($input, $varstring=null) {
        if (! preg_match("/^[0-9]{2}-[0-9]{2}-[0-9]{2}$/", $input)) {
            trigger_error("Date provided is not a valid value", E_USER_ERROR);
        }
    }

    static public function isTime($input, $varstring=null) {
        if (! preg_match("/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/", $input)) {
            trigger_error("Time provided is not a valid value", E_USER_ERROR);
        }
    }
}

?>
