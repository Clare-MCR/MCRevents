#!/usr/bin/env python
# -*- coding: utf-8 -*-
# Flask configuration
import logging
import os
import sys
import traceback
from functools import wraps
from wsgiref.handlers import CGIHandler

import flask
from flask.json import JSONEncoder

from dbops import ravenUserNames, ravenUsers

FORMAT = '%(asctime)-15s %(message)s'

DEBUG = False
SECRET_KEY = 'development moooo key'

app = flask.Flask(__name__)
app.config.from_object(__name__)
logging.getLogger(app.logger_name)
logging.basicConfig(filename='logs/mealbooker.log', level=logging.DEBUG, format=FORMAT)
app.logger.debug('Logger Initialised')
app.logger.info(sys.version)


class CustomJSONEncoder(JSONEncoder):
    def default(self, obj):
        if isinstance(obj, Passport):
            # Implement code to convert Passport object to a dict
            return passport_dict
        else:
            JSONEncoder.default(self, obj)

# Now tell Flask to use the custom class
app.json_encoder = CustomJSONEncoder


def format_exception(e):
    info = ''.join(traceback.format_tb(sys.exc_info()[2]))
    return str(e) + "\n\n" + info


def display_errors(func):
    @wraps(func)
    def dec(*args, **kwargs):
        app.logger.debug(display_errors.__name__)
        try:
            return func(*args, **kwargs)
        except Exception as e:
            if DEBUG:
                app.logger.error('{} {}'.format(type(e).__name__, format_exception(e)))
                return flask.render_template('errorinfo.html', errorName=type(e).__name__,
                                             traceback=format_exception(e))
            else:
                app.logger.error('error here')
                return flask.render_template('errorinfo.html')

    return dec


def require_login(func):
    @wraps(func)
    @display_errors
    def dec(*args, **kwargs):
        app.logger.debug(require_login.__name__)
        if not flask.session.get('logged_in'):
            app.logger.debug("We're not logged in")
            app.logger.info('going to {}'.format(flask.url_for('login')))
            return flask.redirect(flask.url_for('login'))
        app.logger.debug("We're Back")
        return func(*args, **kwargs)

    return dec


def require_admin(func):
    @wraps(func)
    @display_errors
    def dec(*args, **kwargs):
        app.logger.debug(require_admin.__name__)
        if not flask.session.get('logged_in'):
            return flask.redirect(flask.url_for('login'))
        if not flask.session['user'].isAdmin:
            flask.flash('Administrator privileges required for that action!', 'error')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def require_admin_for_admin_booking(func):
    @wraps(func)
    @display_errors
    def dec(*args, **kwargs):
        app.logger.debug(require_admin_for_admin_booking.__name__)
        if 'isAdminBooking' in kwargs:
            is_admin_booking = kwargs['isAdminBooking']
        else:
            is_admin_booking = False
        isAdmin = flask.session['user'].isAdmin
        if is_admin_booking and not isAdmin:
            flask.flash('Administrator privileges required to make admin booking!', 'error')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def require_event_existing(func):
    @wraps(func)
    @require_login
    def dec(*args, **kwargs):
        app.logger.debug(require_event_existing.__name__)
        event_id = kwargs['eventID']
        from dbops import getEvents
        if len(getEvents(event_id)) == 0:
            flask.flash('Could not find event ' + str(event_id), 'error')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def requireEligibilityForEvent(func):
    @wraps(func)
    @require_event_existing
    def dec(*args, **kwargs):
        app.logger.debug(requireEligibilityForEvent.__name__)
        eventID = kwargs['eventID']
        from dbops import getEvent
        event = getEvent(eventID)
        if not flask.session['user'].isEligibleForEvent(event):
            flask.flash('User not eligible for event!', 'error')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def requireEventOpen(func):
    @wraps(func)
    @require_event_existing
    def dec(*args, **kwargs):
        app.logger.debug(requireEventOpen.__name__)
        if 'isAdminBooking' in kwargs:
            isAdminBooking = kwargs['isAdminBooking']
        else:
            isAdminBooking = False
        eventID = kwargs['eventID']
        from dbops import getEvent
        event = getEvent(eventID)
        eventClosed = not event.isOpen()
        if eventClosed and not isAdminBooking:
            flask.flash('Booking closed for this event!', 'error')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def requireNoBooking(func):
    @wraps(func)
    @requireEligibilityForEvent
    def dec(*args, **kwargs):
        app.logger.debug(requireNoBooking.__name__)
        if 'isAdminBooking' in kwargs:
            isAdminBooking = kwargs['isAdminBooking']
        else:
            isAdminBooking = False
        eventID = kwargs['eventID']
        from dbops import isUserBookedInEvent
        if isUserBookedInEvent(eventID, userID=flask.session['user'].userID, isAdminBooking=isAdminBooking):
            flask.flash('You\'ve already booked this event!', 'error')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def requireBooking(func):
    @wraps(func)
    @requireEligibilityForEvent
    def dec(*args, **kwargs):
        app.logger.debug(requireBooking.__name__)
        if 'isAdminBooking' in kwargs:
            isAdminBooking = kwargs['isAdminBooking']
        else:
            isAdminBooking = False
        eventID = kwargs['eventID']
        from dbops import isUserBookedInEvent
        if not isUserBookedInEvent(eventID, userID=flask.session['user'].userID, isAdminBooking=isAdminBooking):
            flask.flash('You haven\'t already booked this event!', 'error')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def requireGuestSpacesLeftForUser(func):
    @wraps(func)
    @requireBooking
    def dec(*args, **kwargs):
        app.logger.debug(requireGuestSpacesLeftForUser.__name__)
        if 'isAdminBooking' in kwargs:
            isAdminBooking = kwargs['isAdminBooking']
        else:
            isAdminBooking = False
        eventID = kwargs['eventID']
        from dbops import areFreeGuestSpacesForUser
        if not areFreeGuestSpacesForUser(eventID, userID=flask.session['user'].userID, isAdminBooking=isAdminBooking):
            flask.flash('You have no free guest slots for this event!', 'error')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def requireEmptyQueue(func):
    @wraps(func)
    @requireBooking
    def dec(*args, **kwargs):
        app.logger.debug(requireEmptyQueue.__name__)
        eventID = kwargs['eventID']
        from dbops import numPeopleInQueueForEvent
        numQueued = numPeopleInQueueForEvent(eventID)
        if numQueued > 0:
            flask.flash('There are people in the queue!', 'error')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def requireNotInQueue(func):
    @wraps(func)
    @requireEligibilityForEvent
    def dec(*args, **kwargs):
        app.logger.debug(requireNotInQueue.__name__)
        eventID = kwargs['eventID']
        from dbops import isUserInQueueForEvent
        if isUserInQueueForEvent(eventID, userID=flask.session['user'].userID):
            flask.flash('You\'re already in the queue for this event!', 'error')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def requireInQueue(func):
    @wraps(func)
    @requireEligibilityForEvent
    def dec(*args, **kwargs):
        app.logger.debug(requireInQueue.__name__)
        eventID = kwargs['eventID']
        from dbops import isUserInQueueForEvent
        if not isUserInQueueForEvent(eventID, userID=flask.session['user'].userID):
            flask.flash('You haven\'t joined the queue for this event!', 'error')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def cancellable(func):
    @wraps(func)
    def dec(*args, **kwargs):
        app.logger.debug(cancellable.__name__)
        if flask.request.form['action'] == 'Cancel':
            flask.flash('Operation cancelled')
            return flask.redirect(flask.url_for('eventselector'))
        return func(*args, **kwargs)

    return dec


def confirmAction(confirmText):
    def outerDec(func):
        @wraps(func)
        @require_login
        def dec(*args, **kwargs):
            app.logger.debug(confirmAction.__name__)
            if 'confirmProceed' in flask.session and flask.session['confirmProceed']:
                flask.session.pop('confirmProceed', None)
                return func(*args, **kwargs)
            flask.session['confirmFuncName'] = func.__name__
            flask.session['confirmFunc_args'] = args
            flask.session['confirmFunc_kwargs'] = kwargs
            flask.session['confirmProceedPressed'] = False
            return flask.redirect(flask.url_for('confirmActionForm', confirmText=confirmText))

        return dec

    return outerDec


@app.route('/confirmActionForm/<confirmText>')
@require_login
def confirmActionForm(confirmText):
    app.logger.debug(confirmActionForm.__name__)
    return flask.render_template('confirmActionForm.html', confirmText=confirmText)


@app.route('/confirmActionHandler', methods=['POST'])
@cancellable
@require_login
def confirmActionHandler():
    app.logger.debug(confirmActionHandler.__name__)
    if flask.session['confirmProceedPressed']:
        return
    flask.session['confirmProceedPressed'] = True
    funcName = flask.session['confirmFuncName']
    args = flask.session['confirmFunc_args']
    kwargs = flask.session['confirmFunc_kwargs']
    flask.session.pop('confirmFuncName', None)
    flask.session.pop('confirmFunc_args', None)
    flask.session.pop('confirmFunc_kwargs', None)
    flask.session['confirmProceed'] = True
    return globals()[funcName](*args, **kwargs)


@app.route('/', defaults={'showAllEntries': 0})
@app.route('/showAllEntries/<int:showAllEntries>')
@require_login
def eventselector(showAllEntries):
    app.logger.debug(eventselector.__name__)
    from dbops import getEvents, isUserBookedInEvent, isUserInQueueForEvent, numPeopleInQueueForEvent
    from datetime import datetime, timedelta
    app.logger.debug("We're logged in and ready to rumble")
    app.logger.info("Creating user from json")

    #  = user.userID
    #  = user.isAdmin
    # flask.session['isMCRMember'] = user.isMCRMember
    # flask.session['isAssociateMember'] = user.isAssociateMember
    # flask.session['isCRA'] = user.isCRA
    # flask.session['isCollegeBill'] = user.isCollegeBill
    # user = RavenUser(flask.session['userID'], flask.session['isAdmin'], row[2], row[3], row[4], row[5])
    user = flask.session['user']
    events = [x for x in getEvents() if user.isEligibleForEvent(x)]
    currentEvents = events

    cutoffDate = datetime.now() - timedelta(days=1)
    currentEvents = [x for x in events if x.eventDate is not None and x.eventDate > cutoffDate]
    moreEntriesToShow = (len(currentEvents) - len(events))

    if showAllEntries:
        eventsToUse = events
    else:
        eventsToUse = currentEvents

    for event in eventsToUse:
        event.hasUserBooked = isUserBookedInEvent(event.eventID, userID=flask.session['user'].userID)
        event.hasUserBookedAdmin = isUserBookedInEvent(event.eventID, userID=flask.session['user'].userID,
                                                       isAdminBooking=True)
        event.isUserInQueue = isUserInQueueForEvent(event.eventID, userID=flask.session['user'].userID)
        event.numQueued = numPeopleInQueueForEvent(event.eventID)
        event.showQueue = (event.numQueued > 0)
    return flask.render_template('eventselector.html', showAllEntries=showAllEntries,
                                 moreEntriesToShow=moreEntriesToShow, events=eventsToUse)


@app.route('/eventDetails/<int:eventID>')
@requireEligibilityForEvent
def eventDetails(eventID):
    app.logger.debug(eventDetails.__name__)
    from dbops import getEvent, getBookings, getQueueEntries
    event = getEvent(eventID)
    bookings = getBookings(eventID)
    queued = getQueueEntries(eventID)
    showQueue = (len(queued) > 0)
    return flask.render_template('eventDetails.html', event=event, bookings=bookings, queued=queued,
                                 showQueue=showQueue)


@app.route('/bookEvent/<int:eventID>', defaults={'isAdminBooking': 0})
@app.route('/bookEvent/<int:eventID>/<int:isAdminBooking>')
@requireNoBooking
@requireNotInQueue
@requireEventOpen
@require_admin_for_admin_booking
def bookEvent(eventID, isAdminBooking):
    app.logger.debug(bookEvent.__name__)
    from dbops import getEvent
    event = getEvent(eventID)
    return flask.render_template('bookEvent.html', event=event, isAdminBooking=isAdminBooking)


@app.route('/bookEventHandler/<int:eventID>', defaults={'isAdminBooking': 0}, methods=['POST'])
@app.route('/bookEventHandler/<int:eventID>/<int:isAdminBooking>', methods=['POST'])
@requireNoBooking
@requireNotInQueue
@requireEventOpen
@require_admin_for_admin_booking
def bookEventHandler(eventID, isAdminBooking):
    app.logger.debug(bookEventHandler.__name__)
    from dbops import getEvent, makeBookingIfSpace
    event = getEvent(eventID)
    bookingSucceeded = makeBookingIfSpace(event, flask.session['user'], isAdminBooking=isAdminBooking,
                                          numTickets=int(flask.request.form['numTickets']))
    if bookingSucceeded:
        flask.flash('Booking successful!')
        return flask.redirect(flask.url_for('modifyBooking', eventID=eventID, isAdminBooking=isAdminBooking))
    else:
        # flask.flash('Not enough space - join queue?', 'error')
        # return flask.redirect(flask.url_for('joinQueueForEvent', eventID=eventID))
        flask.flash("DON'T PANIC! The event was full but you're in the queue")
        from dbops import joinQueue
        joinQueue(eventID, flask.session['user'], isAdminBooking=False,
                  numTickets=int(flask.request.form['numTickets']))
        return flask.redirect(flask.url_for('modifyQueue', eventID=eventID))


@app.route('/joinQueueForEvent/<int:eventID>')
@requireNoBooking
@requireNotInQueue
@requireEventOpen
def joinQueueForEvent(eventID):
    app.logger.debug(joinQueueForEvent.__name__)
    from dbops import getEvent
    event = getEvent(eventID)
    return flask.render_template('joinQueueForEvent.html', event=event)


@app.route('/joinQueueForEventHandler/<int:eventID>', methods=['POST'])
@requireNoBooking
@requireNotInQueue
@requireEventOpen
def joinQueueForEventHandler(eventID):
    app.logger.debug(joinQueueForEventHandler.__name__)
    # Due to locking, if there is space in the queue, then we are always allowed
    # to try to make a booking -- the queue is filtered upwards on an unbooking
    from dbops import getEvent, makeBookingIfSpace
    event = getEvent(eventID)
    bookingSucceeded = makeBookingIfSpace(event, flask.session['user'], isAdminBooking=False,
                                          numTickets=int(flask.request.form['numTickets']))
    if bookingSucceeded:
        flask.flash('You applied for the queue, but there was space to book you in!')
        return flask.redirect(flask.url_for('modifyBooking', eventID=eventID))

    # Else just join the queue
    from dbops import joinQueue
    joinQueue(eventID, flask.session['user'], isAdminBooking=False, numTickets=int(flask.request.form['numTickets']))
    flask.flash('Joined queue!')
    return flask.redirect(flask.url_for('modifyQueue', eventID=eventID))


@app.route('/modifyQueue/<int:eventID>')
@requireNoBooking
@requireInQueue
@requireEventOpen
def modifyQueue(eventID):
    app.logger.debug(modifyQueue.__name__)
    from dbops import getEvent, getQueueEntry
    event = getEvent(eventID)
    booking = getQueueEntry(eventID, flask.session['user'].userID)
    return flask.render_template('modifyQueue.html', event=event, booking=booking)


@app.route('/modifyQueueHandler/<int:eventID>', methods=['POST'])
@cancellable
@requireNoBooking
@requireInQueue
@requireEventOpen
def modifyQueueHandler(eventID):
    app.logger.debug(modifyQueueHandler.__name__)
    from dbops import getQueueEntry, updateQueueDetails
    booking = getQueueEntry(eventID, flask.session['user'].userID)
    from objectcreation import updateBookingFromForm
    booking = updateBookingFromForm(booking, flask.request.form)
    updateQueueDetails(booking)
    flask.flash('Queue details updated')
    return flask.redirect(flask.url_for('eventselector'))


@app.route('/removeGuestFromQueueHandler/<int:eventID>/<int:bookingID>/<int:detailsID>')
@requireNoBooking
@requireInQueue
@requireEventOpen
@confirmAction('Remove guest from queue?')
def removeGuestFromQueueHandler(eventID, bookingID, detailsID):
    app.logger.debug(removeGuestFromQueueHandler.__name__)
    from dbops import removeGuestFromQueue
    removeGuestFromQueue(eventID, bookingID, detailsID)
    flask.flash('Guest unbooked')
    return flask.redirect(flask.url_for('eventselector'))


@app.route('/leaveQueueForEventHandler/<int:eventID>')
@requireInQueue
@requireEventOpen
@confirmAction('Leave queue?')
def leaveQueueForEventHandler(eventID):
    app.logger.debug(leaveQueueForEventHandler.__name__)
    from dbops import leaveQueue
    leaveQueue(eventID, flask.session['user'].userID)
    flask.flash('Left queue')
    return flask.redirect(flask.url_for('eventselector'))


@app.route('/modifyBooking/<int:eventID>', defaults={'isAdminBooking': 0})
@app.route('/modifyBooking/<int:eventID>/<int:isAdminBooking>')
@requireBooking
@requireEventOpen
@require_admin_for_admin_booking
def modifyBooking(eventID, isAdminBooking):
    app.logger.debug(modifyBooking.__name__)
    from dbops import getEvent, getBooking, numPeopleInQueueForEvent
    event = getEvent(eventID)
    numQueued = numPeopleInQueueForEvent(event.eventID)
    event.blockExtraGuests = (numQueued > 0)
    booking = getBooking(eventID, flask.session['user'].userID, isAdminBooking)
    return flask.render_template('modifyBooking.html', event=event, booking=booking, isAdminBooking=isAdminBooking)


@app.route('/modifyBookingHandler/<int:eventID>', defaults={'isAdminBooking': 0}, methods=['POST'])
@app.route('/modifyBookingHandler/<int:eventID>/<int:isAdminBooking>', methods=['POST'])
@cancellable
@requireBooking
@requireEventOpen
@require_admin_for_admin_booking
def modifyBookingHandler(eventID, isAdminBooking):
    app.logger.debug(modifyBookingHandler.__name__)
    from dbops import getBooking, updateBookingDetails
    booking = getBooking(eventID, flask.session['user'].userID, isAdminBooking)
    from objectcreation import updateBookingFromForm
    booking = updateBookingFromForm(booking, flask.request.form)
    updateBookingDetails(booking)
    flask.flash('Booking details updated')
    return flask.redirect(flask.url_for('eventselector'))


@app.route('/unbookGuestHandler/<int:eventID>/<int:bookingID>/<int:detailsID>', defaults={'isAdminBooking': 0})
@app.route('/unbookGuestHandler/<int:eventID>/<int:bookingID>/<int:detailsID>/<int:isAdminBooking>')
@requireBooking
@requireEventOpen
@require_admin_for_admin_booking
@confirmAction('Unbook guest?')
def unbookGuestHandler(eventID, bookingID, detailsID, isAdminBooking):
    app.logger.debug(unbookGuestHandler.__name__)
    from dbops import unbookGuest
    unbookGuest(eventID, bookingID, detailsID)
    flask.flash('Guest unbooked')
    return flask.redirect(flask.url_for('modifyBooking', eventID=eventID, isAdminBooking=isAdminBooking))


@app.route('/bookAnotherGuest/<int:eventID>/<int:bookingID>', defaults={'isAdminBooking': 0})
@app.route('/bookAnotherGuest/<int:eventID>/<int:bookingID>/<int:isAdminBooking>')
@requireBooking
@requireEmptyQueue
@requireGuestSpacesLeftForUser
@requireEventOpen
@require_admin_for_admin_booking
def bookAnotherGuest(eventID, bookingID, isAdminBooking):
    app.logger.debug(bookAnotherGuest.__name__)
    from dbops import bookAnotherGuestIfSpace
    couldBook = bookAnotherGuestIfSpace(eventID, bookingID, flask.session['user'], isAdminBooking=isAdminBooking)
    if couldBook:
        flask.flash('Extra guest booked')
    else:
        flask.flash('No space left, sorry!', 'error')
    return flask.redirect(flask.url_for('modifyBooking', eventID=eventID, isAdminBooking=isAdminBooking))


@app.route('/unbookEventHandler/<int:eventID>', defaults={'isAdminBooking': 0})
@app.route('/unbookEventHandler/<int:eventID>/<int:isAdminBooking>')
@requireEventOpen
@requireBooking
@require_admin_for_admin_booking
@confirmAction('Unbook from event?')
def unbookEventHandler(eventID, isAdminBooking):
    app.logger.debug(unbookEventHandler.__name__)
    from dbops import deleteBookings
    deleteBookings(eventID, flask.session['user'], isAdminBooking)
    flask.flash('Booking removed')
    return flask.redirect(flask.url_for('eventselector'))


@app.route('/createEvent')
@require_admin
def createEvent():
    app.logger.debug(createEvent.__name__)
    from datetime import date
    import calendar
    from dbops import getEventDefaults
    return flask.render_template('createEvent.html', defaults=getEventDefaults(), months=calendar.month_name,
                                 today=date.today())


@app.route('/createEventHandler', methods=['POST'])
@require_admin
def createEventHandler():
    app.logger.debug(createEventHandler.__name__)
    from objectcreation import eventFromForm
    try:
        event = eventFromForm(flask.request.form)
    except ValueError:
        flask.flash('Bad values in input', 'error')
        # TODO
        # if DEBUG:
        #  flask.flash(
        return flask.redirect(flask.url_for('eventselector'))
    from dbops import storeNewEvent
    storeNewEvent(event)
    flask.flash('Event created!')
    return flask.redirect(flask.url_for('eventselector'))


@app.route('/deleteEventHandler/<int:eventID>')
@require_admin
@confirmAction('Delete event?')
def deleteEventHandler(eventID):
    app.logger.debug(deleteEventHandler.__name__)
    from dbops import deleteEvent
    deleteEvent(eventID)
    flask.flash('Event deleted!')
    return flask.redirect(flask.url_for('eventselector'))


@app.route('/updateHandler/<int:eventID>')
@require_admin
@confirmAction('Update Queue?')
def updateHandler(eventID):
    app.logger.debug(updateHandler.__name__)
    from dbops import updateQueue
    updateQueue(eventID)
    flask.flash('Event updated!')
    return flask.redirect(flask.url_for('eventselector'))


@app.route('/login')
@display_errors
def login():
    app.logger.debug(login.__name__)
    # TODO remove me!
    # app.logger.debug("this is where the problems happen")
    # return flask.redirect(flask.url_for('ravenlogin'))
    # TODO remove me!
    app.logger.info("Checking if we need to login")
    app.logger.debug(flask.session)
    if flask.session.get('logged_in'):
        return flask.redirect(flask.url_for('eventselector'))
    return flask.redirect(flask.url_for('ravenlogin'))  # flask.render_template('login.html')


@app.route('/alternatelogin', methods=['POST'])
@display_errors
def alternatelogin():
    app.logger.debug(alternatelogin.__name__)
    from flask import request, session, flash, redirect, url_for
    if request.form['username'] != 'cow':
        flash('Invalid username', 'error')
    elif request.form['password'] != 'moo':
        flash('Invalid password', 'error')
    else:
        session['logged_in'] = True
        from datatypes import AlternateUser
        session['user'] = AlternateUser('cow', 1, 1, 0, 0, 0)
        flash('You were logged in')
    return redirect(url_for('eventselector'))


@app.route('/ravenloginredirect')
@display_errors
def ravenloginredirect():
    app.logger.debug(ravenloginredirect.__name__)
    url = flask.url_for('ravenloginredirect')
    app.logger.debug(url)
    url = url.replace('redirect', '')
    url = url.replace('mealbooker.py', 'ravenlogin.py')
    app.logger.debug(url)
    return flask.redirect(url)


@app.route('/ravenlogin')
@display_errors
def ravenlogin():
    app.logger.debug(ravenlogin.__name__)
    from flask import redirect, url_for

    # errorurl = flask.url_for('goodbye').replace('ravenlogin.py', 'mealbooker.py')
    # homeurl = flask.url_for('eventselector').replace('ravenlogin.py', 'mealbooker.py')

    crsid = None
    app.logger.debug(os.environ)
    if 'REMOTE_USER' in os.environ:
        crsid = str(os.environ['REMOTE_USER'])

    if crsid is None:
        app.logger.warning('No Raven crsid found!')
        flask.flash('No Raven crsid found!', 'error')
        flask.session['logged_in'] = False
        return redirect(flask.url_for('goodbye'))

    if crsid not in ravenUserNames():
        app.logger.warning('User Not registered')
        flask.flash('User ' + crsid + ' not registered for booking', 'error')
        flask.session['logged_in'] = False
        return redirect(flask.url_for('goodbye'))
    app.logger.debug('user in allowed usernames')
    app.logger.debug('Getting name')
    user = ravenUsers(crsid)[0]
    app.logger.debug(user)

    flask.session['user'] = user\
    #     .displayName()
    # flask.session['userID'] = user.userID
    # flask.session['isAdmin'] = user.isAdmin
    # flask.session['isMCRMember'] = user.isMCRMember
    # flask.session['isAssociateMember'] = user.isAssociateMember
    # flask.session['isCRA'] = user.isCRA
    # flask.session['isCollegeBill'] = user.isCollegeBill

    flask.session['logged_in'] = True
    app.logger.debug('You are logged in')
    flask.flash('You were logged in, {}'.format(flask.session['user']))
    app.logger.debug(flask.session)

    url = url_for('eventselector')
    app.logger.debug(url)
    return redirect(url)


@app.route('/logout')
@require_login
def logout():
    app.logger.debug(logout.__name__)
    flask.session.pop('logged_in', None)
    flask.session.pop('user', None)
    flask.flash('You were logged out')
    return flask.redirect('https://raven.cam.ac.uk/auth/logout.html')


@app.route('/goodbye')
def goodbye():
    app.logger.debug(goodbye.__name__)
    return flask.render_template('goodbye.html')


@app.errorhandler(404)
def page_not_found(error):
    """
    The server has not found anything matching the Request-URI. No indication
    is given of whether the condition is temporary or permanent. The 410 (Gone)
    status code SHOULD be used if the server knows, through some internally
    configurable mechanism, that an old resource is permanently unavailable
    and has no forwarding address. This status code is commonly used when the
    server does not wish to reveal exactly why the request has been refused,
    or when no other response is applicable.
    """
    app.logger.debug(page_not_found.__name__)
    return flask.render_template("errorinfo.html"), 404


@app.errorhandler(405)
def method_not_allowed_page(error):
    """
    The method specified in the Request-Line is not allowed for the resource
    identified by the Request-URI. The response MUST include an Allow header
    containing a list of valid methods for the requested resource.
    """
    app.logger.debug(method_not_allowed_page.__name__)
    return flask.render_template("errorinfo.html"), 405


@app.errorhandler(500)
def server_error_page(error):
    app.logger.debug(server_error_page.__name__)
    return flask.render_template("servererror.html"), 500


if __name__ == '__main__':
    """ To allow aptana to receive errors, set use_debugger=False
    # app = create_app(config="config.yaml")
    # if app.debug: use_debugger = True
    # try:
    #   # Disable Flask's debugger if external debugger is requested
    #   use_debugger = not(app.config.get('DEBUG_WITH_APTANA'))
    # except:
    #   pass
    # app.run(use_debugger=use_debugger, debug=app.debug,
    #         use_reloader=use_debugger, host='0.0.0.0')
    """
    app.logger.debug("Starting App")

    # app.run()
    CGIHandler().run(app)
