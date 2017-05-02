<?php
namespace clareevents\functions;
use clareevents\classes\database;
use clareevents\classes\user;

/**
 * Created by PhpStorm.
 * User: rg12
 * Date: 02/05/2017
 * Time: 13:29
 */

function validate_is_date($date) {
	# Unimplemented validator for $date
	return;
}

/**
 * @param $number
 * @param $infostring
 */
function validate_is_number($number, $infostring) {
	# Validates that an input contains numbers only
	if (!preg_match("/^[0-9]+$/", $number)) {
		trigger_error($infostring, E_USER_ERROR);
	}
}

/**
 * @param $name
 * @param $infostring
 */
function validate_name($name, $infostring) {
	# Validates a name to be whitespace, letters, hypens, periods and apostrophes
	if (!preg_match('/^[\w\s-\.\']+$/', stripslashes($name))) {
		trigger_error($infostring, E_USER_ERROR);
	}
}

/**
 * @param $string
 */
function validate_is_alphanumeric($string) {
	# Unimplemented validation for alphanumeric inputs
	return;
}


/**
 * @return string
 */
function is_locked() {
	# Checks whether the database has Y in the 'lock' table. If so returns Y
	
	$dbh = new database();
	
	global $my_pre;
	$dbh->query("SELECT COUNT(*) FROM " . $my_pre . "lock WHERE status=:status");
	$dbh->bind(":status", "Y");
	$result = $dbh->resultset();
	
	if ($result['COUNT(*)'] > 0) {
		return 'Y';
	} else {
		return '';
	}
}

/**
 * @param $eventid
 * @param $crsid
 *
 * @return int
 */
function tickets_ordered($eventid, $crsid) {

	# This counts and returns the # tickets that a user has booked thus far, from queue and booking details.

	global $my_pre;
	$dbh = new database();

	$dbh->query("SELECT COUNT(*) FROM ". $my_pre . "booking_details WHERE eventid=:eventid AND booker=:crsid AND admin='0'");
	$dbh->bind(':eventid', $eventid);
	$dbh->bind(':crsid', $crsid);
	$dbh->resultset();
	$attendees = $dbh->rowCount();

	$dbh->query("SELECT COUNT(*) FROM ". $my_pre . "queue_details WHERE eventid=:eventid AND booker=:crsid AND admin='0'");
	$dbh->bind(':eventid', $eventid);
	$dbh->bind(':crsid', $crsid);
	$dbh->execute();
	$tickets = $attendees + $dbh->rowCount();

	return $tickets;
}

/**
 * @param $eventid
 *
 * @return mixed
 */
function max_tickets($eventid) {

	# Returns the maximum number of tickets a user may have.

	global $my_pre;
	$dbh = new database();

	$dbh->query("SELECT max_guests FROM " . $my_pre . "eventslist WHERE id=:eventid");
	$dbh->bind(":eventid", $eventid);

	$result = $dbh->single();


	# Increment max_guests by one to get the total tickets allowed.
	return $result['max_guests'] + 1;

}

/**
 * @param $guests
 * @param $eventid
 * @param $admin
 * @param user $user
 */
function dietform($guests, $eventid, $admin,user &$user) {

	/* @function dietform
	 * @abstract Prints out a form for the user to fill in the dietary requirements of a guest.
	 * @discussion At present somewhat messy and needs work
	 */

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
		echo "<input type=\"radio\" name=\"tickets[" . $i . "][diet]\" value=\"None+Wine\" id=\"nodiet_" . $i . "\" checked=\"checked\"><label for=\"nodiet_" . $i . "\">None/Not Applicable + wine (£5)</label><br/>";
		echo "<input type=\"radio\" name=\"tickets[" . $i . "][diet]\" value=\"Vegetarian\" id=\"vgt_" . $i . "\"><label for=\"vgt_" . $i . "\">Vegetarian</label><br/>";
		echo "<input type=\"radio\" name=\"tickets[" . $i . "][diet]\" value=\"Vegetarian+Wine\" id=\"vgt_" . $i . "\"><label for=\"vgt_" . $i . "\">Vegetarian + wine (£5)</label><br/>";
		echo "<input type=\"radio\" name=\"tickets[" . $i . "][diet]\" value=\"Vegan\"  id=\"vgn_" . $i . "\"><label for=\"vgn_" . $i . "\">Vegan</label><br/>";
		echo "<input type=\"radio\" name=\"tickets[" . $i . "][diet]\" value=\"Vegan+Wine\"  id=\"vgn_" . $i . "\"><label for=\"vgn_" . $i . "\">Vegan + wine (£5)</label><br/>";
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

/**
 * @param $eventid
 *
 * @return mixed
 */
function get_event_name($eventid) {

	# Returns the event name given the eventid

	global $my_pre;
	$dbh= new database();

	$dbh->query("SELECT name FROM " . $my_pre . "eventslist WHERE id=:eventid");
	$dbh->bind(":eventid", $eventid);

	$result = $dbh->single();

	return $result['name'];

}

/**
 * @param $eventid
 *
 * @return mixed
 */
function get_event_date($eventid) {

	# Returns the event date given the eventid

	global $my_pre;
	$dbh = new database();

	$dbh->query("SELECT event_date FROM " . $my_pre . "eventslist WHERE id=:eventid");
	$dbh->bind(":eventid", $eventid);
	$result= $dbh->single();
	return $result['event_date'];

}

/**
 * @param $crsid
 * @param $eventid
 *
 * @return int
 */
function has_primary($crsid, $eventid) {

	# Checks whether the current user has a primary ticket

	global $my_pre;
	$dbh = new database();
	# This checks the queue_details and booking_details for a primary ticket

	$dbh->query("SELECT COUNT(*) FROM " . $my_pre . "booking_details WHERE eventid=:eventid AND booker=:booker AND type='1'");
	$dbh->bind(":eventid", $eventid);
	$dbh->bind(":booker", $crsid);

	$result = $dbh->single();

	if ($result['COUNT(*)'] > 1) {
		trigger_error("You appear to have more than one primary ticket, please contact the administrator immediately.", E_USER_ERROR);
	} elseif ($result['COUNT(*)'] == 1) {
		return 1;
	} else {
		# Check queue_details

		$dbh->query("SELECT COUNT(*) FROM " . $my_pre . "queue_details WHERE eventid=:eventid AND booker=:booker AND type='1'");
		$dbh->bind(":eventid", $eventid);
		$dbh->bind(":booker", $crsid);
		$result = $dbh->single();

		if ($result['COUNT(*)'] > 1) {
			trigger_error("You appear to have more than one primary ticket, please contact the administrator immediately.", E_USER_ERROR);
		} elseif ($result['COUNT(*)'] == 1) {
			return 1;
		}
	}

	return 0;
}

# Error handling
/**
 * @param $error_level
 * @param $error_message
 */
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