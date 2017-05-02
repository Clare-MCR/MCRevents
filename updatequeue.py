#!/usr/bin/env python
import sys
from dbops import updateQueue
try:
   updateQueue(sys.argv[1])
except (RuntimeError, TypeError, NameError):
   exit("something went wrong")
