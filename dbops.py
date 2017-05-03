import logging

import MySQLdb
import ldap

FORMAT = '%(asctime)s %(levelname)s\t: %(message)s'
logging.basicConfig(filename='logs/mealbooker.log', level=logging.DEBUG, format=FORMAT)

DEBUG = False
if DEBUG:
    pass

# Global variables for table names
# _dbPrefix = 'test_'
_dbPrefix = ''

mcrevents_booking = _dbPrefix + 'mcrevents_booking'
mcrevents_booking_details = _dbPrefix + 'mcrevents_booking_details'
mcrevents_defaults = _dbPrefix + 'mcrevents_defaults'
mcrevents_eventslist = _dbPrefix + 'mcrevents_eventslist'
mcrevents_queue = _dbPrefix + 'mcrevents_queue'
mcrevents_queue_details = _dbPrefix + 'mcrevents_queue_details'


def _getMySQLPassword():
    password = None
    # TODO this will probably need to be changed
    passwordFilename = '/societies/claremcr/mcrpwd.php'
    # passwordFilename = '/home/tpsg2/mcrpwd.php'
    # passwordFilename = '/home/aph36/mcrpwd.php'
    # passwordFilename = '/home/rjg70/mcrpwd.php'
    f = open(passwordFilename)
    for line in f:
        if line.startswith('$pwd'):
            password = line.split("'")[1]
    f.close()
    return password


def getMySQLCursorAndDb():
    # TODO should call cur.close() and db.commit() when we're making modifications
    db = MySQLdb.connect(host='localhost', db='claremcr', user='claremcr', passwd=_getMySQLPassword())
    cur = db.cursor()
    return cur, db


def getMySQLCursor():
    return getMySQLCursorAndDb()[0]


def ravenUserNames():
    cur = getMySQLCursor()
    cur.execute('SELECT crsid FROM access WHERE e_view = 1 AND e_book = 1 AND enabled = 1')
    users = []
    for row in cur.fetchall():
        users.append(row[0])
    return users


def ravenUsers(crsid=None):
    cur = getMySQLCursor()
    logging.debug('ravenUsers: get user')
    if crsid is None:
        cur.execute(
            'SELECT crsid, e_adm, mcr_member, associate_member, cra, college_bill\
             FROM access WHERE e_view = 1 AND e_book = 1 AND enabled = 1')
    else:
        cur.execute(
            'SELECT crsid, e_adm, mcr_member, associate_member, cra, college_bill\
             FROM access WHERE e_view = 1 AND e_book = 1 AND enabled = 1 AND crsid = "{0}"'.format(crsid))
    users = []
    for row in cur.fetchall():
        from datatypes import RavenUser
        user = RavenUser(row[0], row[1], row[2], row[3], row[4], row[5])
        users.append(user)
    return users


def getUser(crsid):
    users = ravenUsers(crsid)
    if len(users) > 1:
        raise Exception('More than one user with crsid=' + crsid)
    return users[0]


def displayNameForCRSID(crsid):

    l = ldap.open('ldap.lookup.cam.ac.uk')
    res = l.search_s('uid={0},ou=people,o=University of Cambridge,dc=cam,dc=ac,dc=uk'.format(str(crsid)),
                     ldap.SCOPE_SUBTREE)

    displayName = ""
    if len(res) > 0:
        try:
            displayName = str(res[0][1]['displayName'][0])
            if displayName == '':
                raise Exception('Blank Display Name')
        except (KeyError, Exception):
            displayName = str(res[0][1]['cn'][0])
        finally:
            logging.debug('User login: {} ({})'.format(displayName, crsid))
        return displayName
    return str(crsid)


def getEvent(eventID):
    events = getEvents(eventID)
    if len(events) > 1:
        raise Exception('More than one event matching eventID=' + str(eventID))
    return events[0]


def getEvents(eventID=None):
    cur = getMySQLCursor()
    if eventID is None:
        cur.execute(
            'SELECT id, name, total_guests, current_guests, max_guests, associate_member, mcr_member, cra,\
             non_clare_associate_member, cost_normal, cost_second, event_date, open_date, close_date, sent\
             FROM {0} ORDER BY event_date ASC'.format(
                mcrevents_eventslist))
    else:
        cur.execute(
            'SELECT id, name, total_guests, current_guests, max_guests, associate_member, mcr_member,\
             cra, non_clare_associate_member, cost_normal, cost_second, event_date, open_date, close_date, sent\
             FROM {0} WHERE id = {1} ORDER BY event_date ASC'.format(mcrevents_eventslist, eventID))
    events = []
    for row in cur.fetchall():
        from datatypes import Event
        event = Event(row[0], row[1], row[2], row[3], row[4], row[5], row[6], row[7], row[8], row[9], row[10], row[11],
                      row[12], row[13], row[14])
        events.append(event)
    return events


def getEventDefaults():
    cur = getMySQLCursor()
    cur.execute('SELECT name, value FROM {0}'.format(mcrevents_defaults))
    from datatypes import EventDefaults
    eventDefaults = EventDefaults()
    # def __init__(self, costPrimary=None, costGuest=None, maxGuests=None, totalTickets=None):
    for row in cur.fetchall():
        if row[0] == 'cost_normal':
            eventDefaults.costPrimary = float(row[1])
        elif row[0] == 'cost_second':
            eventDefaults.costGuest = float(row[1])
        elif row[0] == 'max_guests':
            eventDefaults.maxGuests = int(row[1])
        elif row[0] == 'total_guests':
            eventDefaults.totalTickets = int(row[1])
        else:
            raise Exception('Event defaults: Unknown property name: ' + str(row[0]))
    return eventDefaults


def storeNewEvent(event):
    cur, db = getMySQLCursorAndDb()
    _lockTables(cur, [mcrevents_eventslist])
    cur.execute('INSERT INTO {0} (name, total_guests, current_guests, max_guests, associate_member,\
      mcr_member, cra, non_clare_associate_member, cost_normal, cost_second, event_date, open_date, close_date, sent)\
      VALUES ("{1}", {2}, {3}, {4}, {5}, {6}, {7}, {8}, {9}, {10}, {11}, {12}, {13}, "{14}")\
                '.format(mcrevents_eventslist,
                         event.name,
                         event.totalTickets, 0,
                         event.maxGuests,
                         event.openToAssociateMembers,
                         event.openToMCRMembers,
                         event.openToCRAs,
                         event.openToNonClareAssociateMembers,
                         event.costPrimary,
                         event.costGuest,
                         event.eventDate,
                         event.bookingOpenDate,
                         event.bookingCloseDate,
                         'N'))
    db.commit()
    _unlockTables(cur)
    db.close()


def deleteEvent(eventID):
    cur, db = getMySQLCursorAndDb()
    _lockTables(cur, [mcrevents_booking, mcrevents_booking_details, mcrevents_queue, mcrevents_queue_details,
                      mcrevents_eventslist])
    cur.execute('DELETE FROM {0} WHERE id = {1}'.format(mcrevents_eventslist, eventID))
    cur.execute('DELETE FROM {0} WHERE eventid = {1}'.format(mcrevents_booking, eventID))
    cur.execute('DELETE FROM {0} WHERE eventid = {1}'.format(mcrevents_booking_details, eventID))
    cur.execute('DELETE FROM {0} WHERE eventid = {1}'.format(mcrevents_queue, eventID))
    cur.execute('DELETE FROM {0} WHERE eventid = {1}'.format(mcrevents_queue_details, eventID))
    db.commit()
    _unlockTables(cur)
    db.close()


def isUserBookedInEvent(eventID, userID, isAdminBooking=False):
    cur = getMySQLCursor()
    cur.execute('SELECT id FROM {0} WHERE eventid = {1} AND booker = "{2}" AND admin = {3}'.format(mcrevents_booking,
                                                                                                 eventID, userID,
                                                                                                 isAdminBooking))
    return len(list(cur.fetchall())) > 0


def areFreeGuestSpacesForUser(eventID, userID, isAdminBooking):
    if isAdminBooking:
        return True
    event = getEvent(eventID)
    booking = getBooking(eventID, userID, isAdminBooking)
    return booking.numTickets < (event.maxGuests + 1)


def isUserInQueueForEvent(eventID, userID):
    cur = getMySQLCursor()
    cur.execute('SELECT id FROM {} WHERE eventid = {} AND booker = "{}"'.format(mcrevents_queue, eventID, userID))
    return len(list(cur.fetchall())) > 0


def getBooking(eventID, userID, isAdminBooking=False):
    bookings = getBookings(eventID, userID, isAdminBooking)
    if len(bookings) > 1:
        raise Exception('More than one booking matching eventID=' + str(eventID) + ', userID=' + str(userID))
    return bookings[0]


def getBookings(eventID, userID=None, isAdminBooking=None):
    cur = getMySQLCursor()
    if userID is None:
        if isAdminBooking is None:
            cur.execute('SELECT id, booker, admin, tickets FROM {0} WHERE eventid = {1}'.format(mcrevents_booking,
                                                                                                eventID))
        else:
            cur.execute('SELECT id, booker, admin, tickets FROM {0} WHERE eventid = {1} AND admin = {2}'.format(
                mcrevents_booking, eventID, isAdminBooking))
    else:
        cur.execute(
            'SELECT id, booker, admin, tickets FROM {0} WHERE eventid = {1} AND booker = "{2}" AND admin = {3}'.format(
                mcrevents_booking, eventID, userID, isAdminBooking))
    bookings = []
    for row in cur.fetchall():
        from datatypes import Booking, BookingDetails
        booking = Booking(row[0], row[1], row[2], row[3])
        cur.execute('SELECT type, id, name, diet, other FROM {0} WHERE eventid = {1} AND bookingid = {2}'.format(
            mcrevents_booking_details, eventID, booking.bookingID))
        for thisrow in cur.fetchall():
            details = BookingDetails(thisrow[1], thisrow[2], thisrow[3], thisrow[4])
            if thisrow[0] == '1':
                booking.primaryDetails = details
            else:
                booking.guestDetails.append(details)
        bookings.append(booking)
    return bookings


def numPeopleInQueueForEvent(eventID):
    cur = getMySQLCursor()
    cur.execute('SELECT id FROM {0} WHERE eventid = {1}'.format(mcrevents_queue_details, eventID))
    return len(list(cur.fetchall()))


def getQueueEntry(eventID, userID):
    entries = getQueueEntries(eventID, userID)
    if len(entries) > 1:
        raise Exception('More than one queue entry matching eventID=' + str(eventID) + ' and userID=' + userID)
    return entries[0]


def getQueueEntries(eventID, userID=None):
    cur = getMySQLCursor()
    return _getQueueEntriesWorker(cur, eventID, userID)


def _getQueueEntriesWorker(cur, eventID, userID=None):
    if userID is None:
        cur.execute(
            'SELECT id, booker, admin, tickets FROM {0} WHERE eventid = {1} ORDER BY id ASC'.format(mcrevents_queue,
                                                                                                    eventID))
    else:
        cur.execute(
            'SELECT id, booker, admin, tickets FROM {0} WHERE eventid = {1} AND booker = "{2}" ORDER BY id ASC'.format(
                mcrevents_queue, eventID, userID))
    queueEntries = []
    for row in cur.fetchall():
        from datatypes import Booking, BookingDetails
        queueEntry = Booking(row[0], row[1], row[2], row[3])
        cur.execute('SELECT type, id, name, diet, other FROM {0} WHERE eventid = {1} AND bookingid = {2}'.format(
            mcrevents_queue_details, eventID, queueEntry.bookingID))
        for thisrow in cur.fetchall():
            details = BookingDetails(thisrow[1], thisrow[2], thisrow[3], thisrow[4])
            if thisrow[0] == '1':
                queueEntry.primaryDetails = details
            else:
                queueEntry.guestDetails.append(details)
        queueEntries.append(queueEntry)
    return queueEntries


# TODO FIXME THINK!!!!!!
# def isRoomForBooking(eventID, numTickets):
#   cur = getMySQLCursor()
#   cur.execute('SELECT current_guests FROM {0} WHERE id = {}'.format(mcrevents_eventslist), eventID)
#   current_guests = cur.fetchone()[0]
#   event = getEvent(eventID)
#   isRoom = ((event.totalTickets - current_guests) >= numTickets)
#   return isRoom

def makeBookingIfSpace(event, thisuser, isAdminBooking, numTickets):
    from datatypes import RavenUser
    cur, db = getMySQLCursorAndDb()
    user = RavenUser(thisuser['userID'], thisuser['isAdmin'], thisuser['isMCRMember'],
                     thisuser['isAssociateMember'], thisuser['isCRA'], thisuser['isCollegeBill'])
    logging.debug(user)
    _lockTables(cur, [mcrevents_booking, mcrevents_booking_details, mcrevents_eventslist])
    cur.execute('SELECT current_guests FROM {0} WHERE id = {1}'.format(mcrevents_eventslist, event.eventID))

    current_guests = cur.fetchone()[0]
    # Check that there's room to make the booking, if not bail out
    isRoom = ((event.totalTickets - current_guests) >= numTickets)
    if not isRoom:
        _unlockTables(cur)
        db.close()
        return False

    cur.execute('INSERT INTO {0} (eventid, booker, admin, tickets) \
      VALUES ({1}, "{2}", {3}, {4})'.format(mcrevents_booking,
                                          event.eventID, user.userID, isAdminBooking, numTickets))
    bookingID = db.insert_id()
    # Create blank rows in details table
    for i in range(numTickets):
        # ticketType is '1' for primary, '0' for guest (apparently). is a string...
        ticketType = '0'
        if isAdminBooking:
            name = 'ADMIN BOOKING'
        else:
            name = user.displayName() + ' (Guest ' + str(i) + ')'
        if i == 0:
            ticketType = '1'
            if not isAdminBooking:
                name = user.displayName()
        cur.execute('INSERT INTO {} (bookingid, eventid, booker, admin, type, name,\
        diet, other) \
        VALUES ({}, {}, "{}", {}, {}, "{}", "{}", "{}")'.format(mcrevents_booking_details,
                                                        bookingID, event.eventID, user.userID, isAdminBooking,
                                                        ticketType, name,'None', ''))
    cur.execute('UPDATE {} SET current_guests = {} WHERE id = {}'.format(mcrevents_eventslist,
                                                                         current_guests + numTickets, event.eventID))
    db.commit()
    _unlockTables(cur)
    db.close()
    _notifyUserOfQueueToBooking(user.userID, event.eventID)
    return True


def joinQueue(eventID, thisuser, isAdminBooking, numTickets):
    from datatypes import RavenUser
    user = RavenUser(thisuser['userID'], thisuser['isAdmin'], thisuser['isMCRMember'],
                     thisuser['isAssociateMember'], thisuser['isCRA'], thisuser['isCollegeBill'])
    cur, db = getMySQLCursorAndDb()
    _lockTables(cur, [mcrevents_booking, mcrevents_booking_details, mcrevents_queue, mcrevents_queue_details,
                      mcrevents_eventslist])

    cur.execute('INSERT INTO {} (eventid, booker, admin, tickets) \
      VALUES ({}, "{}", {}, {})'.format(mcrevents_queue,
                                      eventID, user.userID, isAdminBooking, numTickets))
    queueID = db.insert_id()
    # Create blank rows in details table
    for i in range(numTickets):
        # ticketType is '1' for primary, '0' for guest (apparently). is a string...
        ticketType = '0'
        name = user.displayName() + ' (Guest ' + str(i) + ')'
        if i == 0:
            ticketType = '1'
            name = user.displayName()
        cur.execute('INSERT INTO {} (bookingid, eventid, booker, admin, type, name,\
        diet, other) \
        VALUES ({}, {}, "{}", {}, {}, "{}", "{}", "{}")'.format(mcrevents_queue_details,
                                                        queueID, eventID, user.userID, isAdminBooking, ticketType, name,
                                                        'None', ''))
    db.commit()
    shouldNotifyList = _fillEmptySpacesFromQueueWorker(cur, db, eventID)
    _unlockTables(cur)
    db.close()
    _notifyUserEventPairs(shouldNotifyList)


def updateBookingDetails(booking):
    cur, db = getMySQLCursorAndDb()
    _lockTables(cur, [mcrevents_booking_details])
    cur.execute('UPDATE {} SET diet = "{}", other = "{}" WHERE id = {}'.format(mcrevents_booking_details,
                                                                           booking.primaryDetails.diet,
                                                                           booking.primaryDetails.other,
                                                                           booking.primaryDetails.detailsID))
    for guestDetails in booking.guestDetails:
        cur.execute('UPDATE {} SET name = "{}", diet = "{}", other = "{}" WHERE id = {}'.format(mcrevents_booking_details,
                                                                                          guestDetails.name,
                                                                                          guestDetails.diet,
                                                                                          guestDetails.other,
                                                                                          guestDetails.detailsID))
    db.commit()
    _unlockTables(cur)
    db.close()


def updateQueueDetails(booking):
    cur, db = getMySQLCursorAndDb()
    _lockTables(cur, [mcrevents_queue_details])
    cur.execute('UPDATE {} SET diet = "{}", other = "{}" WHERE id = {}'.format(mcrevents_queue_details,
                                                                           booking.primaryDetails.diet,
                                                                           booking.primaryDetails.other,
                                                                           booking.primaryDetails.detailsID))
    for guestDetails in booking.guestDetails:
        cur.execute('UPDATE {} SET name = "{}", diet = "{}", other = "{}" WHERE id = {}'.format(mcrevents_queue_details,
                                                                                          guestDetails.name,
                                                                                          guestDetails.diet,
                                                                                          guestDetails.other,
                                                                                          guestDetails.detailsID))
    db.commit()
    _unlockTables(cur)
    db.close()


def unbookGuest(eventID, bookingID, detailsID):
    cur, db = getMySQLCursorAndDb()
    _lockTables(cur, [mcrevents_booking, mcrevents_booking_details, mcrevents_queue, mcrevents_queue_details,
                      mcrevents_eventslist])

    cur.execute('SELECT tickets FROM {} WHERE id = {}'.format(mcrevents_booking, bookingID))
    numTickets = cur.fetchone()[0]
    cur.execute('SELECT current_guests FROM {} WHERE id = {}'.format(mcrevents_eventslist, eventID))
    current_guests = cur.fetchone()[0]

    cur.execute('DELETE FROM {} WHERE id = {}'.format(mcrevents_booking_details, detailsID))

    cur.execute('UPDATE {} SET current_guests = {} WHERE id = {}'.format(mcrevents_eventslist,
                                                                         current_guests - 1, eventID))
    cur.execute('UPDATE {} SET tickets = {} WHERE id = {}'.format(mcrevents_booking, numTickets - 1, bookingID))

    db.commit()
    shouldNotifyList = _fillEmptySpacesFromQueueWorker(cur, db, eventID)
    _unlockTables(cur)
    db.close()
    _notifyUserEventPairs(shouldNotifyList)


def removeGuestFromQueue(eventID, bookingID, detailsID):
    cur, db = getMySQLCursorAndDb()
    _lockTables(cur, [mcrevents_booking, mcrevents_booking_details, mcrevents_queue, mcrevents_queue_details,
                      mcrevents_eventslist])

    cur.execute('SELECT tickets FROM {} WHERE id = {}'.format(mcrevents_queue, bookingID))
    numTickets = cur.fetchone()[0]

    cur.execute('DELETE FROM {} WHERE id = {}'.format(mcrevents_queue_details, detailsID))
    cur.execute('UPDATE {} SET tickets = {} WHERE id = {}'.format(mcrevents_queue, numTickets - 1, bookingID))

    db.commit()
    shouldNotifyList = _fillEmptySpacesFromQueueWorker(cur, db, eventID)
    _unlockTables(cur)
    db.close()
    _notifyUserEventPairs(shouldNotifyList)


def updateQueue(eventID):
    cur, db = getMySQLCursorAndDb()
    _lockTables(cur, [mcrevents_booking, mcrevents_booking_details, mcrevents_queue, mcrevents_queue_details,
                      mcrevents_eventslist])
    shouldNotifyList = _fillEmptySpacesFromQueueWorker(cur, db, eventID)
    _unlockTables(cur)
    db.close()
    _notifyUserEventPairs(shouldNotifyList)


def bookAnotherGuestIfSpace(eventID, bookingID, thisuser, isAdminBooking=False):
    from datatypes import RavenUser
    user = RavenUser(thisuser['userID'], thisuser['isAdmin'], thisuser['isMCRMember'],
                     thisuser['isAssociateMember'], thisuser['isCRA'], thisuser['isCollegeBill'])
    cur, db = getMySQLCursorAndDb()
    _lockTables(cur, [mcrevents_booking, mcrevents_booking_details, mcrevents_eventslist])

    cur.execute('SELECT tickets FROM {} WHERE id = {}'.format(mcrevents_booking, bookingID))
    numTickets = cur.fetchone()[0]
    cur.execute('SELECT total_guests, current_guests FROM {} WHERE id = {}'.format(mcrevents_eventslist, eventID))
    row = cur.fetchone()
    total_guests = row[0]
    current_guests = row[1]
    if total_guests == current_guests:
        _unlockTables(cur)
        db.close()
        return False

    if isAdminBooking:
        name = 'ADMIN BOOKING'
    else:
        name = user.displayName() + ' (Guest ' + str(numTickets) + ')'
    cur.execute('INSERT INTO {} (bookingid, eventid, booker, admin, type, name, diet, other) \
      VALUES ({}, {}, "{}", {}, {}, "{}", "{}", "{}")'.format(mcrevents_booking_details,
                                                      bookingID, eventID, user.userID, isAdminBooking, 0, name, 'None',
                                                      ''))

    cur.execute('UPDATE {} SET current_guests = {} WHERE id = {}'.format(mcrevents_eventslist,
                                                                         current_guests + 1, eventID))
    cur.execute('UPDATE {} SET tickets = {} WHERE id = {}'.format(mcrevents_booking, numTickets + 1, bookingID))

    db.commit()
    _unlockTables(cur)
    db.close()
    return True


def deleteBookings(eventID, user, isAdminBooking=False):
    cur, db = getMySQLCursorAndDb()
    _lockTables(cur, [mcrevents_booking, mcrevents_booking_details, mcrevents_queue, mcrevents_queue_details,
                      mcrevents_eventslist])

    cur.execute('SELECT tickets FROM {} WHERE eventid = {} AND booker = "{}" AND admin = {}'.format(mcrevents_booking,
                                                                                                  eventID, user.userID,
                                                                                                  isAdminBooking))
    numTickets = cur.fetchone()[0]
    cur.execute('SELECT current_guests FROM {} WHERE id = {}'.format(mcrevents_eventslist, eventID))
    current_guests = cur.fetchone()[0]

    cur.execute('DELETE FROM {} WHERE eventid = {} AND booker = "{}" AND admin = {}'.format(mcrevents_booking,
                                                                                          eventID, user.userID,
                                                                                          isAdminBooking))
    cur.execute('DELETE FROM {} WHERE eventid = {} AND booker = "{}" AND admin = {}'.format(mcrevents_booking_details,
                                                                                          eventID, user.userID,
                                                                                          isAdminBooking))
    cur.execute('UPDATE {} SET current_guests = {} WHERE id = {}'.format(mcrevents_eventslist,
                                                                         current_guests - numTickets, eventID))
    db.commit()
    shouldNotifyList = _fillEmptySpacesFromQueueWorker(cur, db, eventID)
    _unlockTables(cur)
    db.close()
    _notifyUserEventPairs(shouldNotifyList)


def _lockTables(cur, tables):
    if len(tables) == 0:
        return
    command = 'LOCK TABLES '
    command += ', '.join(['{0} WRITE'.format(x) for x in tables])
    cur.execute(command)


def _unlockTables(cur):
    cur.execute('UNLOCK TABLES')


def _fillEmptySpacesFromQueueWorker(cur, db, eventID):
    cur.execute('SELECT total_guests, current_guests FROM {} WHERE id = {}'.format(mcrevents_eventslist, eventID))
    row = cur.fetchone()
    total_guests = row[0]
    current_guests = row[1]
    numFree = total_guests - current_guests
    if numFree == 0:
        return None
    shouldNotifyList = []
    for booking in _getQueueEntriesWorker(cur, eventID):
        numFree = total_guests - current_guests
        if booking.numTickets <= numFree:
            _insertBookingWorker(cur, db, current_guests, eventID, booking)
            current_guests += booking.numTickets
            _leaveQueueWorker(cur, db, eventID, booking.userID)
            shouldNotifyList.append((booking.userID, eventID))
    db.commit()
    return shouldNotifyList


def _notifyUserEventPairs(shouldNotifyList):
    if shouldNotifyList is None:
        return
    for (userID, eventID) in shouldNotifyList:
        _notifyUserOfQueueToBooking(userID, eventID)


def _notifyUserOfQueueToBooking(userID, eventID):
    to = '{0}@cam.ac.uk'.format(userID)
    cc = ''
    subject = 'Booking confirmed'
    user = getUser(userID)
    event = getEvent(eventID)
    if not user.isCollegeBill:
        cc = 'mcr-treasurer@clare.cam.ac.uk'
        body = '''Dear {0},

You have been allocated a ticket for {1} on {2}, for yourself, and for your guest(s) (if requested).

If you wish to pay by college bill in future, please contact mcr-computing@clare.cam.ac.uk

Please transfer the cost of the tickets into the Clare College Graduate Society Account, using "{3}-{1}-Formal" as your payment reference, before the event.
If payment is not received within a week then a penalty will be added and/or your booking rights will be removed.

Account name: Clare College Graduate Society
Bank: HSBC
Sort code: 40-16-08
Account number: 94025067

Ticket costs are {4} for MCR members and {5} for Guests.

Kind regards,
Clare MCR Committee'''.format(user.displayName(), event.name, event.eventDate.strftime('%A, %-d %B at %H:%M'), userID,
                              event.costPrimary, event.costGuest)
    else:
        body = '''Dear {0},

You have been allocated a ticket for {1} on {2}, for yourself, and for your guest(s) (if requested).

Kind regards,
Clare MCR Committee
'''.format(user.displayName(), event.name, event.eventDate.strftime('%A, %-d %B at %H:%M'))
    from emailStuff import sendEmail
    sendEmail(to, subject, body, cc)


def leaveQueue(eventID, userID):
    cur, db = getMySQLCursorAndDb()
    _lockTables(cur, [mcrevents_queue, mcrevents_queue_details])

    _leaveQueueWorker(cur, db, eventID, userID)
    _unlockTables(cur)
    db.close()


def _leaveQueueWorker(cur, db, eventID, userID):
    cur.execute('DELETE FROM {} WHERE eventid = {} AND booker = "{}"'.format(mcrevents_queue, eventID, userID))
    cur.execute('DELETE FROM {} WHERE eventid = {} AND booker = "{}"'.format(mcrevents_queue_details, eventID, userID))
    db.commit()


def _insertBookingWorker(cur, db, current_guests, eventID, booking):
    cur.execute('INSERT INTO {} (eventid, booker, admin, tickets) \
      VALUES ({}, "{}", {}, {})'.format(mcrevents_booking,
                                      eventID, booking.userID, booking.isAdminBooking, booking.numTickets))

    booking.bookingID = db.insert_id()
    _insertDetailsWorker(cur, False, eventID, booking, booking.primaryDetails)
    for guestDetails in booking.guestDetails:
        _insertDetailsWorker(cur, True, eventID, booking, guestDetails)

    cur.execute('UPDATE {} SET current_guests = {} WHERE id = {}'.format(mcrevents_eventslist,
                                                                         current_guests + booking.numTickets, eventID))
    db.commit()


def _insertDetailsWorker(cur, isGuest, eventID, booking, details):
    ticketType = '1'
    if isGuest:
        ticketType = '0'

    cur.execute('INSERT INTO {} (bookingid, eventid, booker, admin, type, name,\
      diet, other) \
      VALUES ({}, {}, "{}", {}, {}, "{}", "{}", "{}")'.format(mcrevents_booking_details,
                                                      booking.bookingID, eventID, booking.userID,
                                                      booking.isAdminBooking, ticketType, details.name,
                                                      details.diet, details.other))
