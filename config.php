<?

/**
 * config.php
 *
 * Includes a series of variables and functions some of which need to
 * be refactored into other files and classes, which handle some of the
 * base functions of the events booker.
 *
 * @author James Clemence <james@jvc26.org>
 *
 */

require_once("Log.php");

# Variables which tell the application where to look
$user = "claremcr";
require_once('/home/rjg70/mcrpwd.php');
$database = "claremcr";
$my_pre = "test_mcrevents_";
$host = "localhost";
$logfile = "/home/rjg70/events.log";

# Create the logger
$logger = &Log::singleton("file", $logfile);

# Make the dbh
try {
    $dbh = new PDO("mysql:host=localhost;dbname=$database", $user, $pwd);
} catch(PDOException $e) {
    $logger->log($e->getMessage(), PEAR_LOG_CRIT);
    die();
}

# Validation functions

function validate_is_date($date) {
    # Unimplemented validator for $date
    continue;
}

function validate_is_number($number, $infostring) {
    # Validates that an input contains numbers only
    if (!preg_match("/^[0-9]+$/", $number)) {
        trigger_error($infostring, E_USER_ERROR);
    }
}

function validate_name($name, $infostring) {
    # Validates a name to be whitespace, letters, hypens, periods and apostrophes
    if (!preg_match("/^[\w\s-\.']+$/", stripslashes($name))) {
        trigger_error($infostring, E_USER_ERROR);
    }
}

function validate_is_alphanumeric($string) {
    # Unimplemented validation for alphanumeric inputs
    continue;
}



function is_locked() {
    # Checks whether the database has Y in the 'lock' table. If so returns Y

    global $my_pre;
    global $dbh;

    $statement = $dbh->prepare("SELECT COUNT(*) FROM " . $my_pre . "lock WHERE status=:status");
    $statement->bindValue(":status", "Y");
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC);

    if ($result['COUNT(*)'] > 0) {
        return 'Y';
    } else {
        return;
    }
}

function tickets_ordered($eventid, $crsid) {
    
    # This counts and returns the # tickets that a user has booked thus far, from queue and booking details.
    
    global $my_pre;
    global $dbh;

    $statement = $dbh->prepare("SELECT COUNT(*) FROM ". $my_pre . "booking_details WHERE eventid=:eventid AND booker=:crsid AND admin='0'");
    $statement->bindValue(':eventid', $eventid);
    $statement->bindValue(':crsid', $crsid);
    $statement->execute();
    $result = $statement->fetch();

    $attendees = $result[0];

    $statement = $dbh->prepare("SELECT COUNT(*) FROM ". $my_pre . "queue_details WHERE eventid=:eventid AND booker=:crsid AND admin='0'");
    $statement->bindValue(':eventid', $eventid);
    $statement->bindValue(':crsid', $crsid);
    $statement->execute();
    $result = $statement->fetch();

    $tickets = $attendees + $result[0];

    return $tickets;
}

function max_tickets($eventid) {

    # Returns the maximum number of tickets a user may have.
    
    global $my_pre;
    global $dbh;

    $statement = $dbh->prepare("SELECT max_guests FROM " . $my_pre . "eventslist WHERE id=:eventid");
    $statement->bindValue(":eventid", $eventid);
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC);


    # Increment max_guests by one to get the total tickets allowed.
    return $result['max_guests'] + 1;

}

function dietform($guests, $eventid, $admin, $user) {

    /* @function dietform
     * @abstract Prints out a form for the user to fill in the dietary requirements of a guest. 
     * @discussion At present somewhat messy and needs work
     */

    global $my_pre;

    # Makes a form for the user to fill in the dietary requirements of a guest. $guests is set to number the repeats.

    echo "<table border=\"0\" class=\"book_event\">\n";
    echo "<tr><th>Guest Type</th><th>Name</th><th>Dietary Requirements</th></tr>\n";
    for ($i = 1; $i <= $guests; $i++) {

	if ($i == 1 && $admin != 1) {
            if (has_primary($_SERVER['REMOTE_USER'], $eventid) == 1) {
                echo "<tr><td>Guest</td>";
                echo "<td><input type=\"text\" name=\"tickets[" . $i . "][name]\" value=\"guest of " . $user->getValue('name') . "\">";
                echo "<input type=\"hidden\" name=\"tickets[" . $i . "][type]\" value=\"0\"></td>";
            } else {
                echo "<tr><td>MCR member</td>";
                echo "<td><input type=\"text\" name=\"tickets[" . $i . "][name]\" value=\"" . $user->getValue('name') . "\">";
                echo "<input type=\"hidden\" name=\"tickets[" . $i . "][type]\" value=\"1\"></td>";
            }
        } else {
            if ($admin == 1) {
	        echo "<tr><td>Admin Guest</td>";
                echo "<td><input type=\"hidden\" name=\"tickets[" . $i . "][name]\" value=\"ADMIN BOOKING\">";
                echo "ADMIN BOOKING";
                echo "<input type=\"hidden\" name=\"tickets[" . $i . "][type]\" value=\"0\"></td>";
            } else {
	        echo "<tr><td>Guest</td>";  
                echo "<td><input type=\"hidden\" name=\"tickets[" . $i . "][type]\" value=\"0\">";
                echo "<input type=\"text\" name=\"tickets[" . $i . "][name]\" value=\"Guest of " . $user->getValue('name') . "\"></td>";
            }
        }
        echo "<td>";
	echo "<input type=\"radio\" name=\"tickets[" . $i . "][diet]\" value=\"None\" id=\"nodiet_" . $i . "\" checked=\"checked\"><label for=\"nodiet_" . $i . "\">None/Not Applicable</label><br/>";
        echo "<input type=\"radio\" name=\"tickets[" . $i . "][diet]\" value=\"Vegetarian\" id=\"vgt_" . $i . "\"><label for=\"vgt_" . $i . "\">Vegetarian</label><br/>";
        echo "<input type=\"radio\" name=\"tickets[" . $i . "][diet]\" value=\"Vegan\"  id=\"vgn_" . $i . "\"><label for=\"vgn_" . $i . "\">Vegan</label><br/>";
	echo "Further requirements: <input type=\"text\" name=\"tickets[" . $i . "][other]\" value=\"\" placeholder=\"e.g. No Nuts\"></td>";
        echo "</tr>\n";

    }
    echo "</table>\n";
    # Here we pass the variables needed for the standard insertion into queue_details
    echo "<p>";
    echo "<input type=\"hidden\" name=\"total_tickets\" value=\"" . $_POST['total_tickets'] . "\">";
    echo "<input type=\"hidden\" name=\"eventid\" value=\"" . $_POST['eventid'] . "\">";
    if ($admin == 1) {
        echo "<input type=\"hidden\" name=\"admin\" value=\"\">";
    }
    echo "<input type=\"submit\" value=\"Submit Application\">";
    echo "</p>";

}

function get_event_name($eventid) {

    # Returns the event name given the eventid

    global $my_pre;
    global $dbh;

    $statement = $dbh->prepare("SELECT name FROM " . $my_pre . "eventslist WHERE id=:eventid");
    $statement->bindValue(":eventid", $eventid);
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC);

    return $result['name'];

}

function get_event_date($eventid) {

    # Returns the event date given the eventid

    global $my_pre;
    global $dbh;

    $statement = $dbh->prepare("SELECT event_date FROM " . $my_pre . "eventslist WHERE id=:eventid");
    $statement->bindValue(":eventid", $eventid);
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC);

    return $result['event_date'];

}

function has_primary($crsid, $eventid) {

    # Checks whether the current user has a primary ticket

    global $my_pre;
    global $dbh;

    # This checks the queue_details and booking_details for a primary ticket

    $statement = $dbh->prepare("SELECT COUNT(*) FROM " . $my_pre . "booking_details WHERE eventid=:eventid AND booker=:booker AND type='1'");
    $statement->bindValue(":eventid", $eventid);
    $statement->bindValue(":booker", $crsid);
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC);

    if ($result['COUNT(*)'] > 1) {
        trigger_error("You appear to have more than one primary ticket, please contact the administrator immediately.", E_USER_ERROR);
    } elseif ($result['COUNT(*)'] == 1) {
        return 1;
    } else {
        # Check queue_details

        $statement = $dbh->prepare("SELECT COUNT(*) FROM " . $my_pre . "queue_details WHERE eventid=:eventid AND booker=:booker AND type='1'");
        $statement->bindValue(":eventid", $eventid);
        $statement->bindValue(":booker", $crsid);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        
        if ($result['COUNT(*)'] > 1) {
            trigger_error("You appear to have more than one primary ticket, please contact the administrator immediately.", E_USER_ERROR);
        } elseif ($result['COUNT(*)'] == 1) {
            return 1;
        }
    }

    return 0;
}

# Error handling
function user_warning_error($error_level, $error_message) {

    # Custom warning handler for the system
    echo "<div class=\"error\">";	
    echo "<b>Error [$error_level]:</b> $error_message <br/>";
    echo "</div>";
    echo "\n<div class=\"footer\">\n";
    echo "&copy; 2009-2010, Clare MCR\n";
    echo "</div>\n";
    echo "</body>\n";
    echo "</html>\n";
    die();
}

# Set the error level for E_USER_ERROR
set_error_handler("user_warning_error", E_USER_ERROR);

?>
