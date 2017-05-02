#!/usr/bin/env python
import os
from wsgiref.handlers import CGIHandler

from flask import session, flash, redirect, url_for

from dbops import ravenUserNames, ravenUsers
from mealbooker import app, display_errors
import logging

FORMAT = '%(asctime)-15s %(message)s'
logging.basicConfig(filename='logs/mealbooker.log', level=logging.DEBUG, format=FORMAT)


@app.route('/ravenlogin')
@display_errors
def ravenlogin():
    errorurl = url_for('goodbye').replace('ravenlogin.py', 'mealbooker.py')
    homeurl = url_for('eventselector').replace('ravenlogin.py', 'mealbooker.py')

    crsid = None
    logging.debug(os.environ)
    if 'REMOTE_USER' in os.environ:
        crsid = str(os.environ['REMOTE_USER'])


    if crsid is None:
        flash('No Raven crsid found!', 'error')
        session['logged_in'] = False
        return redirect(errorurl)

    if crsid not in ravenUserNames():
        flash('User ' + crsid + ' not registered for booking', 'error')
        session['logged_in'] = False
        return redirect(errorurl)

    session['user'] = ravenUsers(crsid)[0]

    session['logged_in'] = True
    flash('You were logged in, ' + session['user'].displayName())
    return redirect(homeurl)


if __name__ == '__main__':

    CGIHandler().run(app)
