#!/usr/bin/env python

# Flask configuration
DEBUG = True
SECRET_KEY = 'development moooo key'

import flask
app = flask.Flask(__name__)
app.config.from_object(__name__)

def formatException(e):
  import traceback, sys
  info = ''.join(traceback.format_tb(sys.exc_info()[2]))
  return str(e) + "\n\n" + info

def displayErrors(func):
  from functools import wraps
  @wraps(func)
  def dec(*args, **kwargs):
    try:
      return func(*args, **kwargs)
    except Exception as e:
      if DEBUG:
        return flask.render_template('errorinfo.html', errorName=type(e).__name__, traceback=formatException(e))
      else:
        return flask.render_template('errorinfo.html')
  return dec

def requireLogin(func):
  from functools import wraps
  @wraps(func)
  @displayErrors
  def dec(*args, **kwargs):
    if not flask.session.get('logged_in'):
      return flask.redirect(flask.url_for('login'))
    return func(*args, **kwargs)
  return dec

def requireAdmin(func):
  from functools import wraps
  @wraps(func)
  @displayErrors
  def dec(*args, **kwargs):
    if not flask.session.get('logged_in'):
      return flask.redirect(flask.url_for('login'))
    if not flask.session['user'].isAdmin:
      flask.flash('Administrator privileges required for that action!', 'error')
      return flask.redirect(flask.url_for('eventselector'))
    return func(*args, **kwargs)
  return dec

def requireAdminForAdminBooking(func):
  from functools import wraps
  @wraps(func)
  @displayErrors
  def dec(*args, **kwargs):
    if 'isAdminBooking' in kwargs:
      isAdminBooking = kwargs['isAdminBooking']
    else:
      isAdminBooking = False
    isAdmin = flask.session['user'].isAdmin
    if isAdminBooking and not isAdmin:
      flask.flash('Administrator privileges required to make admin booking!', 'error')
      return flask.redirect(flask.url_for('eventselector'))
    return func(*args, **kwargs)
  return dec

def requireEventExisting(func):
  from functools import wraps
  @wraps(func)
  @requireLogin
  def dec(*args, **kwargs):
    eventID = kwargs['eventID']
    from dbops import getEvents
    if len(getEvents(eventID)) == 0:
      flask.flash('Could not find event ' + str(eventID), 'error')
      return flask.redirect(flask.url_for('eventselector'))
    return func(*args, **kwargs)
  return dec

def requireEligibilityForEvent(func):
  from functools import wraps
  @wraps(func)
  @requireEventExisting
  def dec(*args, **kwargs):
    eventID = kwargs['eventID']
    from dbops import getEvent
    event = getEvent(eventID)
    if not flask.session['user'].isEligibleForEvent(event):
      flask.flash('User not eligible for event!', 'error')
      return flask.redirect(flask.url_for('eventselector'))
    return func(*args, **kwargs)
  return dec

def requireEventOpen(func):
  from functools import wraps
  @wraps(func)
  @requireEventExisting
  def dec(*args, **kwargs):
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
  from functools import wraps
  @wraps(func)
  @requireEligibilityForEvent
  def dec(*args, **kwargs):
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
  from functools import wraps
  @wraps(func)
  @requireEligibilityForEvent
  def dec(*args, **kwargs):
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
  from functools import wraps
  @wraps(func)
  @requireBooking
  def dec(*args, **kwargs):
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
  from functools import wraps
  @wraps(func)
  @requireBooking
  def dec(*args, **kwargs):
    eventID = kwargs['eventID']
    from dbops import numPeopleInQueueForEvent
    numQueued = numPeopleInQueueForEvent(eventID)
    if numQueued > 0:
      flask.flash('There are people in the queue!', 'error')
      return flask.redirect(flask.url_for('eventselector'))
    return func(*args, **kwargs)
  return dec

def requireNotInQueue(func):
  from functools import wraps
  @wraps(func)
  @requireEligibilityForEvent
  def dec(*args, **kwargs):
    eventID = kwargs['eventID']
    from dbops import isUserInQueueForEvent
    if isUserInQueueForEvent(eventID, userID=flask.session['user'].userID):
      flask.flash('You\'re already in the queue for this event!', 'error')
      return flask.redirect(flask.url_for('eventselector'))
    return func(*args, **kwargs)
  return dec

def requireInQueue(func):
  from functools import wraps
  @wraps(func)
  @requireEligibilityForEvent
  def dec(*args, **kwargs):
    eventID = kwargs['eventID']
    from dbops import isUserInQueueForEvent
    if not isUserInQueueForEvent(eventID, userID=flask.session['user'].userID):
      flask.flash('You haven\'t joined the queue for this event!', 'error')
      return flask.redirect(flask.url_for('eventselector'))
    return func(*args, **kwargs)
  return dec


def cancellable(func):
  from functools import wraps
  @wraps(func)
  def dec(*args, **kwargs):
    if flask.request.form['action'] == 'Cancel':
      flask.flash('Operation cancelled')
      return flask.redirect(flask.url_for('eventselector'))
    return func(*args, **kwargs)
  return dec

def confirmAction(confirmText):
  def outerDec(func):
    from functools import wraps
    @wraps(func)
    @requireLogin
    def dec(*args, **kwargs):
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
@requireLogin
def confirmActionForm(confirmText):
  return flask.render_template('confirmActionForm.html', confirmText=confirmText)

@app.route('/confirmActionHandler', methods=['POST'])
@cancellable
@requireLogin
def confirmActionHandler():
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
@requireLogin
def eventselector(showAllEntries):
  from dbops import getEvents, isUserBookedInEvent, isUserInQueueForEvent, numPeopleInQueueForEvent
  from datetime import datetime, timedelta
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
    event.hasUserBookedAdmin = isUserBookedInEvent(event.eventID, userID=flask.session['user'].userID, isAdminBooking=True)
    event.isUserInQueue = isUserInQueueForEvent(event.eventID, userID=flask.session['user'].userID)
    event.numQueued = numPeopleInQueueForEvent(event.eventID)
    event.showQueue = (event.numQueued > 0)
  return flask.render_template('eventselector.html', showAllEntries=showAllEntries, moreEntriesToShow=moreEntriesToShow, events=eventsToUse)

@app.route('/eventDetails/<int:eventID>')
@requireEligibilityForEvent
def eventDetails(eventID):
  from dbops import getEvent, getBookings, getQueueEntries
  event = getEvent(eventID)
  bookings = getBookings(eventID)
  queued = getQueueEntries(eventID)
  showQueue = (len(queued) > 0)
  return flask.render_template('eventDetails.html', event=event, bookings=bookings, queued=queued, showQueue=showQueue)

@app.route('/bookEvent/<int:eventID>', defaults={'isAdminBooking': 0})
@app.route('/bookEvent/<int:eventID>/<int:isAdminBooking>')
@requireNoBooking
@requireNotInQueue
@requireEventOpen
@requireAdminForAdminBooking
def bookEvent(eventID, isAdminBooking):
  from dbops import getEvent
  event = getEvent(eventID)
  return flask.render_template('bookEvent.html', event=event, isAdminBooking=isAdminBooking)

@app.route('/bookEventHandler/<int:eventID>', defaults={'isAdminBooking': 0}, methods=['POST'])
@app.route('/bookEventHandler/<int:eventID>/<int:isAdminBooking>', methods=['POST'])
@requireNoBooking
@requireNotInQueue
@requireEventOpen
@requireAdminForAdminBooking
def bookEventHandler(eventID, isAdminBooking):
  from dbops import getEvent, makeBookingIfSpace
  event = getEvent(eventID)
  bookingSucceeded = makeBookingIfSpace(event, flask.session['user'], isAdminBooking=isAdminBooking, numTickets=int(flask.request.form['numTickets']))
  if bookingSucceeded:
    flask.flash('Booking successful!')
    return flask.redirect(flask.url_for('modifyBooking', eventID=eventID, isAdminBooking=isAdminBooking))
  else:
    #flask.flash('Not enough space - join queue?', 'error')
    #return flask.redirect(flask.url_for('joinQueueForEvent', eventID=eventID))
    flask.flash("DON'T PANIC! The event was full but you're in the queue")
    from dbops import joinQueue
    joinQueue(eventID, flask.session['user'], isAdminBooking=False, numTickets=int(flask.request.form['numTickets']))
    return flask.redirect(flask.url_for('modifyQueue', eventID=eventID))

@app.route('/joinQueueForEvent/<int:eventID>')
@requireNoBooking
@requireNotInQueue
@requireEventOpen
def joinQueueForEvent(eventID):
  from dbops import getEvent
  event = getEvent(eventID)
  return flask.render_template('joinQueueForEvent.html', event=event)

@app.route('/joinQueueForEventHandler/<int:eventID>', methods=['POST'])
@requireNoBooking
@requireNotInQueue
@requireEventOpen
def joinQueueForEventHandler(eventID):
  # Due to locking, if there is space in the queue, then we are always allowed
  # to try to make a booking -- the queue is filtered upwards on an unbooking
  from dbops import getEvent, makeBookingIfSpace
  event = getEvent(eventID)
  bookingSucceeded = makeBookingIfSpace(event, flask.session['user'], isAdminBooking=False, numTickets=int(flask.request.form['numTickets']))
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
  from dbops import removeGuestFromQueue
  removeGuestFromQueue(eventID, bookingID, detailsID)
  flask.flash('Guest unbooked')
  return flask.redirect(flask.url_for('eventselector'))

@app.route('/leaveQueueForEventHandler/<int:eventID>')
@requireInQueue
@requireEventOpen
@confirmAction('Leave queue?')
def leaveQueueForEventHandler(eventID):
  from dbops import leaveQueue
  leaveQueue(eventID, flask.session['user'].userID)
  flask.flash('Left queue')
  return flask.redirect(flask.url_for('eventselector'))

@app.route('/modifyBooking/<int:eventID>', defaults={'isAdminBooking': 0})
@app.route('/modifyBooking/<int:eventID>/<int:isAdminBooking>')
@requireBooking
@requireEventOpen
@requireAdminForAdminBooking
def modifyBooking(eventID, isAdminBooking):
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
@requireAdminForAdminBooking
def modifyBookingHandler(eventID, isAdminBooking):
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
@requireAdminForAdminBooking
@confirmAction('Unbook guest?')
def unbookGuestHandler(eventID, bookingID, detailsID, isAdminBooking):
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
@requireAdminForAdminBooking
def bookAnotherGuest(eventID, bookingID, isAdminBooking):
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
@requireAdminForAdminBooking
@confirmAction('Unbook from event?')
def unbookEventHandler(eventID, isAdminBooking):
  from dbops import deleteBookings
  deleteBookings(eventID, flask.session['user'], isAdminBooking)
  flask.flash('Booking removed')
  return flask.redirect(flask.url_for('eventselector'))


@app.route('/createEvent')
@requireAdmin
def createEvent():
  from datetime import date
  import calendar
  from dbops import getEventDefaults
  return flask.render_template('createEvent.html', defaults=getEventDefaults(), months=calendar.month_name, today=date.today())

@app.route('/createEventHandler', methods=['POST'])
@requireAdmin
def createEventHandler():
  from objectcreation import eventFromForm
  try:
    event = eventFromForm(flask.request.form)
  except ValueError:
    flask.flash('Bad values in input', 'error')
    #TODO
    #if DEBUG:
    #  flask.flash(
    return flask.redirect(flask.url_for('eventselector'))
  from dbops import storeNewEvent
  storeNewEvent(event)
  flask.flash('Event created!')
  return flask.redirect(flask.url_for('eventselector'))

@app.route('/deleteEventHandler/<int:eventID>')
@requireAdmin
@confirmAction('Delete event?')
def deleteEventHandler(eventID):
  from dbops import deleteEvent
  deleteEvent(eventID)
  flask.flash('Event deleted!')
  return flask.redirect(flask.url_for('eventselector'))

@app.route('/updateHandler/<int:eventID>')
@requireAdmin
@confirmAction('Update Queue?')
def updateHandler(eventID):
  from dbops import updateQueue
  updateQueue(eventID)
  flask.flash('Event updated!')
  return flask.redirect(flask.url_for('eventselector'))

@app.route('/login')
@displayErrors
def login():
  # TODO remove me!
  return flask.redirect(flask.url_for('ravenloginredirect'))
  # TODO remove me!
  if flask.session.get('logged_in'):
    return flask.redirect(flask.url_for('eventselector'))
  return flask.render_template('login.html')

@app.route('/alternatelogin', methods=['POST'])
@displayErrors
def alternatelogin():
  username = flask.request.form['username']
  password = flask.request.form['password']

  from flask import request, session, flash, render_template, redirect, url_for
  if request.form['username'] != 'cow':
    flash('Invalid username', 'error')
  elif request.form['password'] != 'moo':
    flash('Invalid password', 'error')
  else:
    session['logged_in'] = True
    from datatypes import AlternateUser
    session['user'] = AlternateUser('cow', False, True, True, True)
    flash('You were logged in')
  return redirect(url_for('eventselector'))

@app.route('/ravenloginredirect')
@displayErrors
def ravenloginredirect():
  url = flask.url_for('ravenloginredirect')
  url = url.replace('redirect', '')
  url = url.replace('mealbooker.py', 'ravenlogin.py')
  return flask.redirect(url)

@app.route('/logout')
@requireLogin
def logout():
  flask.session.pop('logged_in', None)
  flask.session.pop('user', None)
  flask.flash('You were logged out')
  return flask.redirect('https://raven.cam.ac.uk/auth/logout.html')

@app.route('/goodbye')
def goodbye():
  return flask.render_template('goodbye.html')


if __name__ == '__main__':
  ## # To allow aptana to receive errors, set use_debugger=False
  ## app = create_app(config="config.yaml")

  ## if app.debug: use_debugger = True
  ## try:
  ##   # Disable Flask's debugger if external debugger is requested
  ##   use_debugger = not(app.config.get('DEBUG_WITH_APTANA'))
  ## except:
  ##   pass
  ## app.run(use_debugger=use_debugger, debug=app.debug,
  ##         use_reloader=use_debugger, host='0.0.0.0')

  from wsgiref.handlers import CGIHandler
  CGIHandler().run(app)
