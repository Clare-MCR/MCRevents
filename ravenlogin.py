#!/usr/bin/env python
import os
from wsgiref.handlers import CGIHandler

import flask

from dbops import ravenUserNames, ravenUsers
from mealbooker import app, display_errors



@app.route('/ravenlogin')
@display_errors
def ravenlogin():
    errorurl = flask.url_for('goodbye').replace('ravenlogin.py', 'mealbooker.py')
    homeurl = flask.url_for('eventselector').replace('ravenlogin.py', 'mealbooker.py')

    crsid = None
    app.logger.debug(os.environ)
    if 'REMOTE_USER' in os.environ:
        crsid = str(os.environ['REMOTE_USER'])


    if crsid is None:
        app.logger.warning('No Raven crsid found!')
        flask.flash('No Raven crsid found!', 'error')
        flask.session['logged_in'] = False
        return flask.redirect(errorurl)

    if crsid not in ravenUserNames():
        app.logger.warning('User Not registered')
        flask.flash('User ' + crsid + ' not registered for booking', 'error')
        flask.session['logged_in'] = False
        return flask.redirect(errorurl)
    app.logger.debug('user in allowed usernames')
    app.logger.debug('Getting name')
    flask.session['user'] = ravenUsers(crsid)[0]

    flask.session['logged_in'] = True
    app.logger.debug('You are logged in')
    app.logger.debug(flask.session)
    flask.flash('You were logged in, ' + flask.session['user'].displayName())
    app.logger.debug(homeurl)
    return flask.redirect(homeurl)


if __name__ == '__main__':

    CGIHandler().run(app)
