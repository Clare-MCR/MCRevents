#!/usr/bin/env python

import cgi
import cgitb; cgitb.enable()  # for troubleshooting


print "Content-type: text/html"
print

import subprocess
resultText = subprocess.Popen("ps aux | grep ^claremcr | grep python | grep mealbooker | grep -v grep | awk '{print $2}'", stdout=subprocess.PIPE, shell=True).communicate()[0]
for pid in resultText.split():
  print 'Killing '+pid+'\n'
  subprocess.call('kill -9 '+pid, shell=True)
