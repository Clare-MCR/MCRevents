from mealbooker import app
import json

class User(object):
    def __init__(self, userID, isAdmin, isMCRMember, isAssociateMember, isCRA, isCollegeBill):
        self.userID = userID
        self.isAdmin = isAdmin
        self.isMCRMember = isMCRMember
        self.isAssociateMember = isAssociateMember
        self.isCRA = isCRA
        self.isCollegeBill = isCollegeBill

    def displayName(self):
        raise NotImplementedError

    def isEligibleForEvent(self, event):
        if self.isAdmin:
            return True
        if event.openToAssociateMembers and self.isAssociateMember:
            return True
        if event.openToMCRMembers and self.isMCRMember:
            return True
        if event.openToCRAs and self.isCRA:
            return True
        return False

    def __repr__(self):
        jsonstring = json.dumps({"name":self.displayName(),"userID": self.userID, "isAdmin": self.isAdmin, "isMCRMember": self.isMCRMember,
                    "isAssociateMember": self.isAssociateMember, "isCRA": self.isCRA,
                    "isCollegeBill": self.isCollegeBill})

        # jsonstring = '[{{ "{0}":{{"name":"{1}","isAdmin": {2}, "isMCRMember": {3}, "isAssociateMember": {4}, "isCRA": {5}, "isCollegeBill": {6}}} }}]'.format(
        #     self.displayName(), self.userID, self.isAdmin, self.isMCRMember, self.isAssociateMember, self.isCRA, self.isCollegeBill)
        app.logger.debug(jsonstring)
        return jsonstring


class RavenUser(User):
    def displayName(self):
        if not hasattr(self, 'displayNameCache') or self.displayNameCache is None:
            from dbops import displayNameForCRSID
            self.displayNameCache = displayNameForCRSID(self.userID)
        return self.displayNameCache


class AlternateUser(User):
    def displayName(self):
        # TODO When alternate user structure implemented, store username in
        # appropriate database
        return self.userID


class Event(object):
    def __init__(self, eventID, name, totalTickets, allocatedTickets, maxGuests, openToAssociateMembers,
                 openToMCRMembers, openToCRAs, openToNonClareAssociateMembers, costPrimary, costGuest, eventDate,
                 bookingOpenDate, bookingCloseDate, sent):
        self.eventID = eventID
        self.name = _toUni(name)
        self.totalTickets = totalTickets
        self.allocatedTickets = allocatedTickets
        self.maxGuests = maxGuests
        self.openToAssociateMembers = openToAssociateMembers
        self.openToMCRMembers = openToMCRMembers
        self.openToCRAs = openToCRAs
        self.openToNonClareAssociateMembers = 0
        self.costPrimary = costPrimary
        self.costGuest = costGuest
        self.eventDate = eventDate
        self.bookingOpenDate = bookingOpenDate
        self.bookingCloseDate = bookingCloseDate
        self.sent = False
        if sent == 'Y':
            self.sent = True
        self.dietaryOptions = ['Normal',
                               'Normal + Wine ('+'+5 pounds)',
                               'Vegetarian',
                               'Vegetarian + Wine ('+'+5 pounds)',
                               'Pesco Vegetarian',
                               'Pesco Vegetarian + Wine ('+'+5 pounds)',
                               'Vegan',
                               'Vegan + Wine ('+'+5 pounds)',
                               'Lactose Free',
                               'Lactose Free + Wine ('+'+5 pounds)',
                               'Gluten Free',
                               'Gluten Free + Wine ('+'+5 pounds)',
                               'Nut Allergy',
                               'Nut Allergy + Wine ('+'+5 pounds)',
                               'Hallal',
                               'Hallal + Wine ('+'+5 pounds)',
                               'No Beef',
                               'No Beef + Wine ('+'+5 pounds)',
                               'No Pork',
                               'No Pork + Wine ('+'+5 pounds)',
                               'No Fish',
                               'No Fish + Wine ('+'+5 pounds)',
                               'Diametic',
                               'Diametic + Wine ('+'+5 pounds)',
                               'Other',
                               'Other + Wine ('+'+5 pounds)']

    def isOpen(self):
        from datetime import datetime
        today = datetime.today()
        return (self.bookingOpenDate <= today) and (today <= self.bookingCloseDate)

    def __repr__(self):
        return '{0}: {1}/{2}. openToMCRMembers: {3}, openToAssociateMembers: {4}, openToCRAs: {5}, openToNonClareAssociateMembers: {6}. {7}'.format(
            self.name, self.allocatedTickets, self.totalTickets, self.openToMCRMembers, self.openToAssociateMembers,
            self.openToCRAs, self.openToNonClareAssociateMembers, self.eventDate)


class EventDefaults(object):
    def __init__(self, costPrimary=None, costGuest=None, maxGuests=None, totalTickets=None):
        self.costPrimary = costPrimary
        self.costGuest = costGuest
        self.maxGuests = maxGuests
        self.totalTickets = totalTickets

    def __repr__(self):
        return 'costPrimary: {0}, costGuest: {1}, maxGuests: {2}, totalTickets: {3}'.format(self.costPrimary,
                                                                                            self.costGuest,
                                                                                            self.maxGuests,
                                                                                            self.totalTickets)


class Booking(object):
    def __init__(self, bookingID, userID, isAdminBooking, numTickets, primaryDetails=None, guestDetails=None):
        self.bookingID = bookingID
        self.userID = userID
        self.isAdminBooking = isAdminBooking
        self.numTickets = numTickets
        self.primaryDetails = primaryDetails
        self.guestDetails = guestDetails
        if guestDetails is None:
            self.guestDetails = []


class BookingDetails(object):
    def __init__(self, detailsID, name, diet, other):
        self.detailsID = detailsID
        self.name = _toUni(name)
        self.diet = _toUni(diet)
        self.other = _toUni(other)


def _toUni(text, encoding='latin-1'):
    if isinstance(text, basestring):
        if not isinstance(text, unicode):
            text = unicode(text, encoding)
    return text
