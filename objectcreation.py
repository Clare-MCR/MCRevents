def eventFromForm(form):
  eventDate = reconstructDatetime(form, 'event')
  bookingOpenDate = reconstructDatetime(form, 'bookingOpen')
  bookingCloseDate = reconstructDatetime(form, 'bookingClose')

  from datatypes import Event
  event = Event(eventID=0, \
      name=form['name'], \
      totalTickets=int(form['totalTickets']), \
      allocatedTickets=0, \
      maxGuests=int(form['maxGuests']), \
      openToAssociateMembers=('AssociateMembers' in form.getlist('openTo')), \
      openToMCRMembers=('MCRMembers' in form.getlist('openTo')), \
      openToCRAs=('CRAs' in form.getlist('openTo')), \
      openToNonClareAssociateMembers=('NonClareAssociateMembers' in form.getlist('openTo')), \
      costPrimary=float(form['costPrimary']), \
      costGuest=float(form['costGuest']), \
      eventDate=eventDate, bookingOpenDate=bookingOpenDate, bookingCloseDate=bookingCloseDate, sent='N')
  return event


def reconstructDatetime(form, prefix):
  from datetime import datetime
  return datetime(int(form[prefix+'Year']), \
                  int(form[prefix+'Month']), \
                  int(form[prefix+'Day']), \
                  int(form[prefix+'Hour']), \
                  int(form[prefix+'Minute']))

def updateBookingFromForm(booking, form):
  booking.primaryDetails.diet = form['primaryDiet']
  booking.primaryDetails.other = form['primaryOther']
  for guestDetails in booking.guestDetails:
    guestPrefix = 'guest'+str(guestDetails.detailsID)
    guestDetails.name = form[guestPrefix+'Name']
    guestDetails.diet = form[guestPrefix+'Diet']
    guestDetails.other = form[guestPrefix+'Other']
  return booking

