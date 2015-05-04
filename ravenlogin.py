#!/usr/bin/env python

from mealbooker import app, displayErrors

@app.route('/ravenlogin')
@displayErrors
def ravenlogin():
  from flask import request, session, flash, render_template, redirect, url_for
  errorurl = url_for('goodbye').replace('ravenlogin.py', 'mealbooker.py')
  homeurl = url_for('eventselector').replace('ravenlogin.py', 'mealbooker.py')

  crsid = None
  import os
  if 'REMOTE_USER' in os.environ:
    crsid = str(os.environ['REMOTE_USER'])

  if crsid is None:
    flash('No Raven crsid found!', 'error')
    session['logged_in'] = False
    return redirect(errorurl)

  from dbops import ravenUserNames
  if crsid not in ravenUserNames():
    flash('User ' + crsid + ' not registered for booking', 'error')
    session['logged_in'] = False
    return redirect(errorurl)
  
  from dbops import ravenUsers
  session['user'] = ravenUsers(crsid)[0]

  session['logged_in'] = True
  flash('You were logged in, ' + session['user'].displayName())
  return redirect(homeurl)

if __name__ == '__main__':
  from wsgiref.handlers import CGIHandler
  CGIHandler().run(app)
