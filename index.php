<?

/**
 * index.php the main file for the booking system
 *
 * This contains the majority of the booking system code, drawing on the 
 * classes available in class_lib and the config and basic functions in
 * config.php. Again, this is subject to a series of refactorings which
 * will be implemented with time.
 *
 * @author James Clemence <james@jvc26.org>
 *
 */

session_unset();

include( 'class_lib.php' );
require_once('config.php');

# First do some User checks.

# # Are we logged in with Raven?

if (! isset($_SERVER['REMOTE_USER'])) {
    trigger_error("User is not logged in with Raven, something is wrong.", E_USER_ERROR);
}

# Initiate the new user object
$user = new User();

# Get user info given the Raven crsid, if they don't exist, exit with error.
if ($user->getFromCRSID($_SERVER['REMOTE_USER']) == False) {
    trigger_error("User does not exist on this system. Please contact the administrators.", E_USER_ERROR);
}

# Check user isnt disabled
if ($user->has_perm('enabled') != True) {
    trigger_error("User disabled, please contact administrators", E_USER_ERROR);
}

# Check that the user has view permissions.
if (! $user->has_perm('e_view')) {
    trigger_error("User does not have view permissions on this system.", E_USER_ERROR);
}

# User has view permissions, check that Allocator isnt running.

if (is_locked() == 'Y') {
    trigger_error("System Locked for Allocator Run, please try back later.", E_USER_ERROR);
}

# Initiate the html and head tags
echo "<!DOCTYPE html>\n";
echo "<html>\n";
echo "<head>\n";
echo "<title>The Clare MCR Events Booking System</title>\n";
echo "<link type=\"text/css\" rel=\"stylesheet\" href=\"events.css\">\n";
echo "<!--[if lt IE 8]>";
echo "<link type=\"text/css\" rel=\"stylesheet\" href=\"ie_old.css\">";
echo "<![endif]-->\n";
echo "<meta http-equiv=\"Content-Type\" content=\"text/html;charset=utf-8\" >\n";
echo "</head>\n";
# And kickstart the body
echo "<body>\n";
echo "<div id=\"mainheader\">\n";
echo "<h1>The Clare MCR Booking System</h1>\n";
echo "</div>\n";    
    
# Check whether user is an admin, and if so, give admin greeting

if ($user->has_perm('e_adm') or $user->has_perm('s_adm')) {
    echo "<div id=\"user_welcome\">";
    echo "Welcome Administrator " . $user->getValue('name') . " <a href='index.php'>Go to Main</a><a href='admin.php'>Go to Admin</a></div>\n";
} else {
    echo "<div id=\"user_welcome\">Welcome " . $user->getValue('name') . "<a href='index.php'>Go to Main</a></div>\n";
}

mainStuff($user);

# Assuming we don't have a die() called, this will close of body and html.
echo "\n<div class=\"footer\">\n";
echo "&copy; 2009-2010, Clare MCR";
echo "</div>\n";
echo "</body>\n";
echo "</html>\n";

function mainStuff($user) {
    if (isset($_POST['event_select'])) {
        booking_form($_POST['eventid'], $user);
    } elseif (isset($_POST['bf_select'])) {
        booking_form($_POST['eventid'], $user);
    } elseif (isset($_POST['details'])) {
        book_event($_POST['eventid'], $_POST['total_tickets'], $user);
    } elseif (isset($_POST['guestlist'])) {
        guestlist($_POST['eventid']);
    } elseif (isset($_POST['editbooking'])) {
        edit_booking($_POST['eventid'], $user);
    } elseif (isset($_POST['editpending'])) {
        edit_pending($_POST['eventid'], $user);
    } else {
        greet();
        display_events($user);
    }
}

function greet() {

}

function display_events($user) {

    # This needs to be linked in to the new 'identity' checks which will be forced with new type events
    
    global $my_pre;
    global $dbh;

    if ($user->has_perm('e_adm') or $user->has_perm('s_adm')) {
        echo "<div id=\"adminview\">\n";
        echo "<h2>Current Events Available for Administrator Booking</h2>\n";

        $statement = $dbh->prepare("SELECT id FROM " . $my_pre . "eventslist WHERE event_date > NOW() AND close_date > NOW() ORDER BY event_date");
        $statement->execute();
        $result = $statement->fetchAll();

        if (count($result) < 1) {
            echo "<p>There are no events upcoming, to create one, <a href=\"admin.php\">visit the admin pages</a>.</p>\n";
        } else {
            # echo "<p class=\"note\">Please note, number of guests shown in \"Tickets Booked\" is correct as of the last run of the Allocator.</p>";
            for ($j = 0; $j < count($result); $j++) {
                $admin = 1;
                $event = new Event();
                $event->getEventFromID($result[$j]['id']);
                $event->displayEvent();
                $event->displayBookingControls($user, $admin);
            }
        }
        echo "</div>";
    }

    # Display events for a normal user to book - in addition to admins - allowing both normal and admin tickets to be bought. 

    echo "<h2>Upcoming Events</h2>";
    
    # Collect the ids of all yet-to-occur events
    
    $statement = $dbh->prepare("SELECT id FROM " . $my_pre . "eventslist WHERE open_date < NOW() AND event_date > NOW() ORDER BY event_date");
    $statement->execute();
    
    $result = $statement->fetchAll(PDO::FETCH_ASSOC);

    # Sift them to find out the permissions, and if they allow a user of the type we have here, allow booking

    $foundevent = 0;
    
    foreach ($result as $eventid) {
        $admin = 0;
        $event = new Event();
        $event->getEventFromID($eventid['id']);

        $mcr_allowed = $event->getValue('mcr_member');
        $fourth_allowed = $event->getValue('4th_year');
        $cra_allowed = $event->getValue('cra');

        # If an mcr member is allowed, and the user is a member, print booking controls.
        if (($mcr_allowed == 1) && ($user->getValue('mcr_member') == 1)) {
            $foundevent = 1;
            $event->displayEvent();
            $event->displayBookingControls($user, $admin);

        # User isn't an mcr member, or the event doesn't allow for them, so what about 4ths?
        } elseif (($fourth_allowed == 1) && ($user->getValue('4th_year') == 1)) {
            $foundevent = 1;
            $event->displayEvent();
            $event->displayBookingControls($user, $admin);

        # And finally what about CRAs when a user isn't either mcr_member or 4th_year
        } elseif (($cra_allowed == 1) && ($user->getValue('cra') == 1)) {
            $foundevent = 1;
            $event->displayEvent();
            $event->displayBookingControls($user, $admin);
        }
    }
    
    if ($foundevent == 0) {
        echo "<p>Unfortunately there are no events available to book right now.</p>";
    }
}    

function booking_form($eventid, $user) {

    global $my_pre;
    global $dbh;

    if (!$user->has_perm('e_book') or !$user->has_perm('enabled')) {
        trigger_error("User does not have permission to book, or is disabled.", E_USER_ERROR);
    }

    # Validate that the event id is a number

    validate_is_number($eventid, "The Given Event ID is not a number, please correct.");

    echo "<h1>Booking for \"" . get_event_name($eventid) . "\"</h1>\n";

    # Breaks off to bring to the rest of the booking form where details are put in.
    # Check whether tickets are needed:

    if (isset($_POST['total_tickets'])) {

        # Validate the crsid and total tickets before placing into database queue

        if (!preg_match('/^[a-z0-9]+$/', $user->getValue('crsid'))) {
            trigger_error("CRSid is invalid, please contact the administrator.", E_USER_ERROR);
        }

        validate_is_number($_POST['total_tickets'], "Total tickets is non-numerical, please press back and correct.");

        # Now make a pretty form for each of the user's tickets.
        echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
        echo "<input type=\"hidden\" name=\"details\">";

        if (isset($_POST['admin'])) {
                
            if (!$user->has_perm('e_adm')) {
                if (!$user->has_perm('s_adm')) {
                    trigger_error("You're not actually an admin.", E_USER_ERROR);
                }
            }
            echo "<p>Administrator Block Booking for event \"" . get_event_name($eventid) . "\" on ". date('d/m/Y',strtotime(get_event_date($eventid))) .".</p>";

            dietform($_POST['total_tickets'], $_POST['eventid'], $admin=1, $user);
            
        } else {
            dietform($_POST['total_tickets'], $_POST['eventid'], $admin=0, $user);
        }
        echo "</form>";

    } else {

        # Allow the user to select number of tickets, taken from the allocation of their quota remaining.
        # Run a check to see whether they are an administrator, and if so... bend the rules.

        if (!preg_match("/^YES$/", $_POST['admin'])) {

            # Count the number of tickets we've ordered thus far, and max_tickets

            $tickets_ordered = tickets_ordered($eventid, $_SERVER['REMOTE_USER']);
            $max_tickets = max_tickets($eventid);

            # Then work out how many the booker can have, if they've hit their quota, exit.

            if ($tickets_ordered >= $max_tickets) {
                trigger_error("I'm afraid you've met your quota of tickets for this event.", E_USER_ERROR);
            } elseif ($tickets_ordered == 0) {
                $tickets_remaining = $max_tickets;
            } else {
                $tickets_remaining = $max_tickets - $tickets_ordered;
            }

            echo "<p class=\"note\">Please note, here you are applying for tickets. Allocation will take place according to number of applicants, in order of arrival. When you request a number of tickets, you will be entered into the allocation queue at that point.</p>\n";

            # They can have some more tickets, allow them to select how many

            echo "<p>For this event you may apply for a further " . $tickets_remaining . " tickets.</p>\n";

            # Create a form for selecting those tickets

            echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">\n";

            echo "<p>\n";
            if (! $tickets_ordered > 0) {
                echo "Please select the number of tickets you would like: ";
            } else {
                echo "Please select the number of additional tickets you would like: ";
            }

            echo "<select name=\"total_tickets\" value=\"\">";
            for ($j = 1; $j <= $tickets_remaining; $j++) {
                echo "<option value=$j>$j</option>";
            }
            echo "</select>";
            echo "<input type=\"hidden\" name=\"eventid\" value=\"" . $eventid ."\">";
            echo "<input type=\"hidden\" name=\"bf_select\" value=\"\">";
            echo "<td> <input type=\"submit\" value=\"Request Tickets\">\n";
            echo "</p>\n";
            echo "</form>\n";
            
            echo "<hr/>";
            echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
            echo "<input type=\"submit\" value=\"Return to Main\">";
            echo "</form>";         
        
        } else {

            # Check we're an admin for real (not just pretending)

            if ($user->has_perm('e_adm') or $user->has_perm('s_adm')) {

                $statement = $dbh->prepare("SELECT total_guests-current_guests FROM " . $my_pre . "eventslist WHERE id=:eventid");
                $statement->bindValue(":eventid", $eventid);
                $statement->execute();

                $result = $statement->fetch(PDO::FETCH_ASSOC);

                $tickets_remaining = $result['total_guests-current_guests'];

		# check that we actually have any tickets remaining
		if($tickets_remaining==0) {
			trigger_error("The event is currently fully booked, and no further tickets can be ordered.", E_USER_ERROR);
		}

                echo "<p>You may book a maximum " . $tickets_remaining . " tickets for this event. This is the total number.</p>";

                echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">\n";

                echo "<p>Please select the number of tickets you would like: ";
                echo "<select name=\"total_tickets\" value=\"\">";

                for ($j = 1; $j <= $tickets_remaining; $j++) {
                    echo "<option value=$j>$j</option>";
                }

                echo "</select>";
                echo "<input type=\"hidden\" name=\"eventid\" value=\"" . $eventid ."\">";
                echo "<input type=\"hidden\" name=\"bf_select\" value=\"\">";
                echo "<input type=\"hidden\" name=\"admin\" value=\"\">";
                echo " <input type=\"submit\" value=\"Request Tickets\"></p>\n";
                echo "</form>\n";

                # Back to Main button
                echo "<hr/>";
                echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
                echo "<input type=\"submit\" value=\"Return to Main\">";
                echo "</form>";         

            } else {
                trigger_error("You're not really an admin.", E_USER_ERROR);
            }
        }
    }
}

function book_event($eventid, $total_tickets, $user) {

    global $my_pre;
    global $dbh;

    if (!$user->has_perm('e_book')) {
        trigger_error("User does not have booking permissions.", E_USER_ERROR);
    }

    # Here we take the incoming information and place it in the booking queue.

    # Check total ticket and eventid numbers are numerical

    validate_is_number($eventid, "The Event ID is not a number, please correct.");

    validate_is_number($total_tickets, "The total number of tickets ordered is not a number, please correct.");

    # Validate the information provided for the guests in the guest form

    if (! isset($_POST['tickets'])) {
        trigger_error("No ticket details appear to have been provided to the system", E_USER_ERROR);
    }

    foreach($_POST['tickets'] as $ticket) {
        if ($ticket['name'] == '') {
            trigger_error("You have not provided one of the names. Press 'Back' to correct.", E_USER_ERROR);
            
        } else {
            validate_name($ticket['name'], "One of the names includes non alphanumeric characters, inverted commas, periods and hyphens. Press 'Back' to correct.");
        }

        # Check diet has been provided:
            
        if (!preg_match("/^(None|Vegetarian|Vegan)$/", $ticket['diet'])) {
            trigger_error("You have not provided a dietary type for a guest, Press 'Back' to correct.", E_USER_ERROR);
        }

        # Check Other requirements, if set, is alphanumeric only.

        if ($ticket['other'] != '') {
            if (!preg_match("/^[\w\s]+$/", $ticket['other'])) {
                trigger_error("Other field contains non alphanumeric characters, Press 'Back' to correct.", E_USER_ERROR);
            }
        }
    }

    # Ok, validation all ok, begin the booking.

    # Has the user already booked max_tickets (if non-admin)?

    if (! isset($_POST['admin'])) {
        $max_tickets = max_tickets($eventid);
        $tickets_ordered = tickets_ordered($eventid, $user->getValue('crsid'));

        if (($tickets_ordered + $total_tickets) > $max_tickets) {
            trigger_error("Adding this number of tickets will take you over the ticket limit for this event.", E_USER_ERROR);
        }
    }

    # Is the event open for booking?

    $event = new Event();
    $event->getEventFromID($eventid);

    if (strtotime($event->getValue('close_date')) < time()) {
        trigger_error("Unfortunately it appears that booking for the event " . $event->getValue('name') . " is closed.", E_USER_ERROR);
    }

    # Format the event_date

    $event_date = date('d/m/Y',strtotime($event->getValue('event_date')));
    
    # Create body and headers for the confirmation email

    $to = $_SERVER['REMOTE_USER'] . "@cam.ac.uk";
    $subject = "MCR Event Booker - Confirmation of Application for \"" . $event->getValue('name') . "\"";
    $body = "Dear MCR member,\n\n";
    $body = $body . "This is to confirm that the following tickets have been applied for \"" . $event->getValue('name') . "\" on " . $event_date . ".\n\nYou will be informed at the next run of the Allocator if you have been successful.\r\n\n";

    # Generate the booker - change their name to *-adm if necessary

    if (isset($_POST['admin'])) {
        if ($user->has_perm('e_adm') or $user->has_perm('s_adm')) {
            $admin = 1;
        } else {
            trigger_error("You're not actually an admin, cancelled.", E_USER_ERROR);
        }
    } else {
        $admin = 0;
    }

    # Set booker
    $booker = $_SERVER['REMOTE_USER'];

    # Insert the person into the queue.

    $statement = $dbh->prepare("INSERT INTO " . $my_pre . "queue (eventid, booker, admin, tickets) VALUES (:eventid, :booker, :admin, :tickets)");
    $statement->bindValue(":eventid", $eventid);
    $statement->bindValue(":booker", $booker);
    $statement->bindValue(":admin", $admin);
    $statement->bindValue(":tickets", $total_tickets);

    $statement->execute();

    # Get the bookingid from the database

    $statement = $dbh->prepare("SELECT LAST_INSERT_ID()");
    $statement->execute();

    $result = $statement->fetch();   
    $bookingid = $result['LAST_INSERT_ID()'];

    # Now for the tickets, insert them into queue_details

    foreach ($_POST['tickets'] as $ticket) {

        # Do the bookings for us, and confirm to the user we have done so
        
        $guest_name = stripslashes($ticket['name']);
        $guest_name = htmlentities($guest_name, ENT_QUOTES);

        $other_req = stripslashes($ticket['other']);
        $other_req = htmlentities($other_req, ENT_QUOTES);

        # Now enter them into the queue for the Allocator, tied to $bookingid
        
        $statement = $dbh->prepare("INSERT INTO " . $my_pre . "queue_details (bookingid, eventid, booker, admin, type, name, diet, other) VALUES (:bookingid,:eventid,:booker,:admin,:type,:name,:diet,:other)");
        $statement->bindValue(":bookingid", $bookingid);
        $statement->bindValue(":eventid", $eventid);
        $statement->bindValue(":booker", $booker);
        $statement->bindValue(":admin", $admin);
        $statement->bindValue(":type", $ticket['type']);
        $statement->bindValue(":name", $guest_name);
        $statement->bindValue(":diet", $ticket['diet']);
        $statement->bindValue(":other", $other_req);

        $statement->execute();
            
        $body = $body . "Name: " . $ticket['name'] . "\r\n Dietary choice: " . $ticket['diet'] . ".";
            
        echo "<p>Ticket Requested for: " . $ticket['name'] . "</p>";
        echo "<p>Diet is " . $ticket['diet'] . "</p>";
            
        if ($ticket['other'] != '') {
            echo "<p>With special requirement: " . $ticket['other'] . "</p>";
            $body = $body . "\r\n Special requirement " . $ticket['other'] . ".\r\n\n";
        } else {
            $body = $body . "\r\n\n";
        }
            echo "<HR>";
    }

    # Sign the message from the MCR Events Boooker

    $body = $body . "Yours, the MCR Events Booker";

    # Mail the user to let them know they have successfully booked.

    mail($to, $subject, $body, ("Content-type: text/plain; charset=ISO-8859-1; format=flowed\r\nContent-Transfer-Encoding: quoted-printable"));

    # And print the return button
    echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
    echo "<input type=\"submit\" value=\"Return to Main\">";
    echo "</form>";
}


function guestlist($eventid) {

    global $my_pre;
    global $dbh;
    
    if (!preg_match("/^[0-9]+$/", $eventid)) {
        trigger_error("The Event Id is not a number, please correct.", E_USER_ERROR);
    }

    echo "<h1>Guestlist for \"" . get_event_name($eventid) . "\"</h1>\n";
    echo "<p>This is the guestlist for event \"" . get_event_name($eventid) . "\" on " . date('d/m/Y',strtotime(get_event_date($eventid))) . ".";
    
    $statement = $dbh->prepare("SELECT COUNT(*) FROM " . $my_pre . "booking_details WHERE eventid=:eventid");
    $statement->bindValue(":eventid", $eventid);
    $statement->execute();
    
    $result = $statement->fetch();
    
    $num_guests = $result[0];
    
    $statement = $dbh->prepare("SELECT * FROM " . $my_pre . "booking_details WHERE eventid=:eventid ORDER BY booker,type DESC,id");
    $statement->bindValue(":eventid", $eventid);
    $statement->execute();
    
    $result = $statement->fetchAll(PDO::FETCH_ASSOC);
    
    if($num_guests>0) { 
        echo "<ol class=\"guest_list\">\n";
        for ($j = 0; $j < $num_guests; $j++) {
                echo "<li>" . $result[$j]['name'] . "</li>\n";
        }
        echo "</ol>";
    } else {
        echo "<div class=\"error\">";
        echo "<b>Error: </b> No tickets have yet been allocated for this event.";
        echo "</div>";
    }
    
    echo "<hr/>";
    echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";

    # Only display the Book Event if the event is open

    $event = new Event();
    $event->getEventFromID($eventid);

    if (strtotime($event->getValue('close_date')) > time()) {
        echo "<input type=\"submit\" name=\"bf_select\" value=\"Book Event\"> ";
    }

    echo "<input type=\"submit\" value=\"Return to Menu\">";
    echo "<input type=\"hidden\" name=\"eventid\" value=\"" . $eventid . "\">";    
    echo "</form>";

}

function edit_booking($eventid, $user) {
    
    global $my_pre;
    global $dbh;

    # At this point trigger if an eventid and a ticket id have been selected for deletion.
    
    if (isset($_POST['ticketarray'])) {

        $primaryDelete = 0;

        foreach ($_POST['ticketarray'] as $targetticket) {

            # Iterate through looking for deletion flags on primary tickets.

            if (isset($targetticket['delete'])) {

                if (!preg_match('/^[0-9]+$/', $targetticket['id'])) {
                    trigger_error("Ticket id nonnumerical, please correct.", E_USER_ERROR);
                }

                $ticket = new Booking_Ticket();
                $ticket->getFromID($targetticket['id']);

                if ($ticket->getValue('type') == 1) {
                    $primaryDelete = 1;
                    $primaryTicket = $ticket->getValue('id');
                }
            }
        }

        # Now we know whether we're talking about primary/other tickets

        if ($primaryDelete == 1) {

            # Do our primary deletion routine
            # Delete the primary ticket's booking (taking out the primary ticket and any subs)
            # Check for orphaned other bookings and remove them as appropriate.
            
            # Initiate our objects

            $ticket = new Booking_Ticket();
            $booking = new Booking();

            # Load up their variables from the db
            $ticket->getFromID($primaryTicket);
            $booking->getFromID($ticket->getValue('bookingid'));

            # Get the tickets associated with the application
            $booking->getTickets();
            $targettickets = $booking->getValue('ticket_objects');

            # Remove all the assoc tickets
            foreach ($targettickets as $targetticket) {
                $targetticket->delete();

                # Decrement the eventslist counter
                $statement = $dbh->prepare("UPDATE " . $my_pre . "eventslist SET current_guests=current_guests-1 WHERE id=:eventid");
                $statement->bindValue(":eventid", $booking->getValue('eventid'));
                $statement->execute();

                $booking->setValue('tickets', ($booking->getValue('tickets') - 1));
                $booking->commit();
            }

            # Remove the booking
            $booking->delete();

            # Now search for orphans
            # All remaining bookings for this event will *not* have primary tickets, so we can just find and remove them
            # This also includes removing orphaned pending bookings

            $statement = $dbh->prepare("SELECT id FROM " . $my_pre . "booking WHERE booker=:booker AND eventid=:eventid AND admin='0'");
            $statement->bindValue(":booker", $user->getValue('crsid'));
            $statement->bindValue(":eventid", $booking->getValue('eventid'));
            $statement->execute();

            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

            if (count($result) == 0) {
                echo "<p>No orphaned bookings remain.</p>";
            } else {
                echo "<p>Orphaned booking found, removing.</p>";

                foreach ($result as $target) {
                    # Get the target's id
                    $targetbookingid = $target['id'];

                    # Set up and initiate the object
                    $targetbooking = new Booking();
                    $targetbooking->getFromID($targetbookingid);
                    $targetbooking->getTickets();

                    # Get its subsequent tickets
                    $targettickets = $targetbooking->getValue('ticket_objects');

                    # Remove the tickets
                    foreach ($targettickets as $targetticket) {
                        $targetticket->delete();

                        # Decrement the counter in the eventslist
                        $statement = $dbh->prepare("UPDATE " . $my_pre . "eventslist SET current_guests=current_guests-1 WHERE id=:eventid");
                        $statement->bindValue(":eventid", $booking->getValue('eventid'));
                        $statement->execute();

                        $targetbooking->setValue('tickets', ($targetbooking->getValue('tickets') - 1));
                    }

                    # Remove the application
                    $targetbooking->delete();
                }
            }

            # Then clear out Orphaned queued applications

            $statement = $dbh->prepare("SELECT id FROM " . $my_pre . "queue WHERE booker=:booker AND eventid=:eventid AND admin='0'");
            $statement->bindValue(":booker", $user->getValue('crsid'));
            $statement->bindValue(":eventid", $booking->getValue('eventid'));
            $statement->execute();

            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

            if (count($result) == 0) {
                echo "<p>No orphaned applications remain.</p>";
            } else {
                echo "<p>Orphaned application found, removing.</p>";

                foreach ($result as $target) {
                    # Get the target's id
                    $targetapplicationid = $target['id'];

                    # Set up and initiate the object
                    $targetapplication = new Application();
                    $targetapplication->getFromID($targetapplicationid);
                    $targetapplication->getTickets();

                    # Get its subsequent tickets
                    $targettickets = $targetapplication->getValue('ticket_objects');

                    # Remove the tickets
                    foreach ($targettickets as $targetticket) {
                        $targetticket->delete();
                        $targetapplication->setValue('tickets', ($targetapplication->getValue('tickets') - 1));
                    }

                    # Remove the application
                    $targetapplication->delete();
                }
            }
        
        } else {

            # Do the deletion/edit routine for normal tickets
            
            foreach ($_POST['ticketarray'] as $targetticket) {

                # We are editing/deleting a single ticket. Note, this may cause the removal of the booking, 
                # if the booking is for one ticket only, or whether it is a primary ticket.

                # Validation of the info we've got is needed

                validate_is_number($targetticket['bookingid'], "Booking id nonnumerical, please correct.");

                validate_is_number($targetticket['eventid'], "Ticket ID is nonnumerical, please correct");

                validate_name($targetticket['name'], "Application name contains nonalphanumerical characters, periods or hyphens.");

                if (!preg_match('/^(None|Vegetarian|Vegan)$/', $targetticket['diet'])) {
                    trigger_error("Application diet is not an approved option.", E_USER_ERROR);
                }

                if ($_POST['appother'] != '') {
                    if (!preg_match('/^[\w\s]+$/', $targetticket['other'])) {
                        trigger_error("Application other option contains non alphanumeric characters", E_USER_ERROR);
                    }
                }

                # Ok, so all our inputs are validated.

                $ticket = new Booking_Ticket();
                $ticket->getFromID($targetticket['id']);

                # If we're deleting the ticket peel off here:

                if (isset($targetticket['delete'])) {

                    # Check whether this is an only ticket

                    $booking = new Booking();
                    $booking->getFromID($ticket->getValue('bookingid'));

                    if ($booking->getValue('tickets') <= 1) {

                        # Delete the single ticket and the application
                        $ticket->delete();

                        # Decrement the eventslist counter
                        $statement = $dbh->prepare("UPDATE " . $my_pre . "eventslist SET current_guests=current_guests-1 WHERE id=:eventid");
                        $statement->bindValue(":eventid", $booking->getValue('eventid'));
                        $statement->execute();

                        $booking->delete();
                    
                    } else {

                        # More than one ticket in this application
                        $ticket->delete();

                        # Decrement the eventslist counter
                        $statement = $dbh->prepare("UPDATE " . $my_pre . "eventslist SET current_guests=current_guests-1 WHERE id=:eventid");
                        $statement->bindValue(":eventid", $booking->getValue('eventid'));
                        $statement->execute();

                        $booking->setValue('tickets', ($booking->getValue('tickets') - 1));

                        if ($booking->getValue('tickets') < 1) {
                            $booking->delete();
                        } else {
                            $booking->commit();
                        }
                    }
                
                } else {

                    # Escape string our verbose options

                    $appname = stripslashes($targetticket['name']);
                    $appname = htmlentities($appname, ENT_QUOTES);

                    $appdiet = stripslashes($targetticket['diet']);
                    $appdiet = htmlentities($appdiet, ENT_QUOTES);

                    $appother = stripslashes($targetticket['other']);
                    $appother = htmlentities($appother, ENT_QUOTES);

                    # Is this an admin ticket we're editing?

                    if ($ticket->getValue('admin') == 1) {
    
                        # Things need handling a little differently

                        # Check the user is an admin

                        if (! $user->has_perm('e_adm')) {
                            if (! $user->has_perm('s_adm')) {
                                trigger_error("User does not have permissions to perform this action.", E_USER_ERROR);
                            }
                        }

                        # Then update the ticket with the new info Note NAMES ARE NOT EDITABLE for ADMIN BOOKINGS

                        $ticket->setValue('diet', $appdiet);
                        $ticket->setValue('other', $appother);


                    } else {

                        # Update our ticket with the new info

                        $ticket->setValue('name', $appname);
                        $ticket->setValue('diet', $appdiet);
                        $ticket->setValue('other', $appother);

                    }

                    # Commit the changes to the db

                    $ticket->commit();

                    # And let the user know whats going on

                    echo "<p>Ticket information successfully updated</p>";
                    echo "<p>Name: " . $ticket->getValue('name') . "</p>";
                    echo "<p>Diet: " . $ticket->getValue('diet') . "</p>";
                    if ($ticket->getValue('other') != '') {
                        echo "<p>Other: " . $ticket->getValue('other') . "</p>";
                    }
                }
            }       
        }
        
        # Provide a button to return home
        echo "<hr/>";
        echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
        echo "<input type=\"submit\" value=\"Return to Main\">";
        echo "</form>";


    } else {

        # Display the booking edit form

        # If we're an admin give us our selection pane

        if (isset($_POST['admin'])) {

            # Check we're an admin

            if (! $user->has_perm('e_adm')) {
                if (! $user->has_perm('s_adm')) {
                    trigger_error("User lacks admin permissions.", E_USER_ERROR);
                }
            }

            $event =  new Event;
            $event->getEventFromID($eventid);
            echo "<h1>Edit admin bookings for \"" . $event->getValue('name') . "\"</h1>";

            $statement = $dbh->prepare("SELECT id FROM " . $my_pre . "booking WHERE eventid=:eventid AND admin='1'");
            $statement->bindValue(":eventid", $eventid);
            $statement->execute();

            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

            # Iterate through the ids, generate application objects and get the print on.

            foreach ($result as $bookingres) {
                $booking = new Booking;
                $booking->getFromID($bookingres['id']);
                $booking->getTickets();
                $booking->displayEdit();
            }
        
        } else {     

            # Print out the selection of Applications to modify NOTE FOR NORMAL USER

            $event = new Event;
            $event->getEventFromID($eventid);
            echo "<h1>Edit your bookings for \"" . $event->getValue('name') . "\"</h1>";

            echo "<p class=\"note\">Please note that removal of the primary tickets will also remove all associated sub-tickets.</p>";

            $statement = $dbh->prepare("SELECT id FROM " . $my_pre . "booking WHERE booker=:booker AND eventid=:eventid AND admin='0'");
            $statement->bindValue(":booker", $_SERVER['REMOTE_USER']);
            $statement->bindValue(":eventid", $eventid);
            $statement->execute();

            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

            # Iterate through the ids, generate application objects and get the print on.

            foreach ($result as $bookingres) {
                $booking = new Booking;
                $booking->getFromID($bookingres['id']);
                $booking->getTickets();
                $booking->displayEdit();
            }
            
            
        }
        echo "<hr/>";

        # Back to home button 
        echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
        echo "<input type=\"submit\" value=\"Return to Main\">";
        echo "</form>";
    }
}

function edit_pending($eventid, $user) {

    global $my_pre;
    global $dbh;

    # Cut off here to do the editing/deletion if necessary
    
    if (isset($_POST['ticketarray'])) {

        $primaryDelete = 0;

        foreach ($_POST['ticketarray'] as $targetticket) {

            # Iterate through looking for deletion flags on primary tickets.

            if (isset($targetticket['delete'])) {

                if (!preg_match('/^[0-9]+$/', $targetticket['id'])) {
                    trigger_error("Ticket id nonnumerical, please correct.", E_USER_ERROR);
                }

                $ticket = new Application_Ticket();
                $ticket->getFromID($targetticket['id']);

                if ($ticket->getValue('type') == 1) {
                    $primaryDelete = 1;
                    $primaryTicket = $ticket->getValue('id');
                }
            }
        }

        # Now we know whether we're talking about primary/other tickets

        if ($primaryDelete == 1) {

            # Do our primary deletion routine
            # Delete the primary ticket's booking (taking out the primary ticket and any subs)
            # Check for orphaned other bookings and remove them as appropriate.
            
            # Initiate our objects

            $ticket = new Application_Ticket();
            $application = new Application();

            # Load up their variables from the db
            $ticket->getFromID($primaryTicket);
            $application->getFromID($ticket->getValue('bookingid'));

            # Get the tickets associated with the application
            $application->getTickets();
            $targettickets = $application->getValue('ticket_objects');

            # Remove all the assoc tickets
            foreach ($targettickets as $targetticket) {
                $targetticket->delete();
                $application->setValue('tickets', ($application->getValue('tickets') - 1));
                $application->commit();
            }

            # Remove the application
            $application->delete();

            # Now search for orphans
            # All remaining bookings for this event will *not* have primary tickets, so we can just find and remove them
            
            $statement = $dbh->prepare("SELECT id FROM " . $my_pre . "queue WHERE booker=:booker AND eventid=:eventid AND admin='0'");
            $statement->bindValue(":booker", $user->getValue('crsid'));
            $statement->bindValue(":eventid", $application->getValue('eventid'));
            $statement->execute();

            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

            if (count($result) == 0) {
                echo "<p>No orphaned bookings remain.</p>";
            } else {
                echo "<p>Orphaned booking found, removing.</p>";

                foreach ($result as $target) {
                    # Get the target's id
                    $targetapplicationid = $target['id'];

                    # Set up and initiate the object
                    $targetapplication = new Application();
                    $targetapplication->getFromID($targetapplicationid);
                    $targetapplication->getTickets();

                    # Get its subsequent tickets
                    $targettickets = $targetapplication->getValue('ticket_objects');

                    # Remove the tickets
                    foreach ($targettickets as $targetticket) {
                        $targetticket->delete();
                        $targetapplication->setValue('tickets', ($application->getValue('tickets') - 1));
                    }

                    # Remove the application
                    $targetapplication->delete();
                }
            }
        
        } else {

            # Do the deletion/edit routine for normal tickets

            foreach ($_POST['ticketarray'] as $targetticket) {

                # We are editing/deleting a single ticket. Note, this may cause the removal of the booking, 
                # if the booking is for one ticket only, or whether it is a primary ticket.

                # Validation of inputted data

                validate_is_number($targetticket['bookingid'], "Booking id nonnumerical, please correct.");

                validate_is_number($targetticket['eventid'], "Ticket ID is nonnumerical, please correct");

                validate_name($targetticket['name'], "Application name contains nonalphanumerical characters, inverted commas, hyphens and periods.");

                if (!preg_match('/^(None|Vegetarian|Vegan)$/', $targetticket['diet'])) {
                    trigger_error("Application diet is not an approved option.", E_USER_ERROR);
                }

                if ($_POST['appother'] != '') {
                    if (!preg_match('/^[\w\s]+$/', $targetticket['other'])) {
                        trigger_error("Application other option contains non alphanumeric characters", E_USER_ERROR);
                    }
                }

                # Ok, so all our inputs are validated.

                $ticket = new Application_Ticket();
                $ticket->getFromID($targetticket['id']);

                # If we're deleting the ticket peel off here:

                if (isset($targetticket['delete'])) {

                    # Check whether this is an only ticket

                    $application = new Application();
                    $application->getFromID($ticket->getValue('bookingid'));

                    if ($application->getValue('tickets') == 1) {

                        # Delete the single ticket and the application
                        $ticket->delete();
                        $application->delete();
                    
                    } else {

                        # More than one ticket in this application
                        $ticket->delete();   
                        $application->setValue('tickets', ($application->getValue('tickets') - 1));
                        $application->commit();
                    }
                
                } else {

                    # Escape string our verbose options
                    
                    $appname = stripslashes($targetticket['name']);
                    $appname = htmlentities($appname, ENT_QUOTES);


                    $appdiet = stripslashes($targetticket['diet']);
                    $appdiet = htmlentities($appdiet, ENT_QUOTES);

                    $appother = stripslashes($targetticket['other']);
                    $appother = htmlentities($appother, ENT_QUOTES);

                    # Is this an admin ticket we're editing?

                    if ($ticket->getValue('admin') == 1) {
    
                        # Things need handling a little differently

                        # Check the user is an admin

                        if (! $user->has_perm('e_adm')) {
                            if (! $user->has_perm('s_adm')) {
                                trigger_error("User does not have permissions to perform this action.", E_USER_ERROR);
                            }
                        }

                        # Then update the ticket with the new info Note NAMES ARE NOT EDITABLE for ADMIN BOOKINGS

                        $ticket->setValue('diet', $appdiet);
                        $ticket->setValue('other', $appother);


                    } else {

                        # Update our ticket with the new info

                        $ticket->setValue('name', $appname);
                        $ticket->setValue('diet', $appdiet);
                        $ticket->setValue('other', $appother);

                    }

                    # Commit the changes to the db

                    $ticket->commit();

                    # And let the user know whats going on

                    echo "<p>Ticket information successfully updated</p>";
                    echo "<p>Name: " . $ticket->getValue('name') . "</p>";
                    echo "<p>Diet: " . $ticket->getValue('diet') . "</p>";
                    if ($ticket->getValue('other') != '') {
                        echo "<p>Other: " . $ticket->getValue('other') . "</p>";
                    }
                }
            }       
        }
        
        # Provide a button to return home
        echo "<hr/>";
        echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
        echo "<input type=\"submit\" value=\"Return to Main\">";
        echo "</form>";

    } else {

        # If we're an admin give us our selection pane

        if (isset($_POST['admin'])) {

            # Check we're an admin

            if (! $user->has_perm('e_adm')) {
                if (! $user->has_perm('s_adm')) {
                    trigger_error("User lacks admin permissions.", E_USER_ERROR);
                }
            }

            $event =  new Event;
            $event->getEventFromID($eventid);
            echo "<h1>Edit admin applications for \"" . $event->getValue('name') . "\"</h1>";

            $statement = $dbh->prepare("SELECT id FROM " . $my_pre . "queue WHERE eventid=:eventid AND admin='1'");
            $statement->bindValue(":eventid", $eventid);
            $statement->execute();

            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

            # Iterate through the ids, generate application objects and get the print on.

            foreach ($result as $event) {
                $application = new Application;
                $application->getFromID($event['id']);
                $application->getTickets();
                $application->displayEdit();
            }
        
        } else {     

            # Print out the selection of Applications to modify NOTE FOR NORMAL USER

            $event = new Event;
            $event->getEventFromID($eventid);
            echo "<h1>Edit your applications for \"" . $event->getValue('name') . "\"</h1>";

            echo "<p class=\"note\">Please note that removal of the primary tickets will also remove all associated sub-tickets.</p>";

            $statement = $dbh->prepare("SELECT id FROM " . $my_pre . "queue WHERE booker=:booker AND eventid=:eventid AND admin='0'");
            $statement->bindValue(":booker", $_SERVER['REMOTE_USER']);
            $statement->bindValue(":eventid", $eventid);
            $statement->execute();

            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

            # Iterate through the ids, generate application objects and get the print on.

            foreach ($result as $event) {
                $application = new Application;
                $application->getFromID($event['id']);
                $application->getTickets();
                $application->displayEdit();
            }
            
            
        }

        echo "<hr/>";
        echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
        echo "<input type=\"submit\" value=\"Return to Main\">";
        echo "</form>";
        
    }
}

?>
