#!/usr/bin/python

# Quick system to generate applications for tickets for testing.

import MySQLdb
import logging
import sys
import random
import hashlib
from random import choice

# Database constants
DB_HOST = "127.0.0.1"
DB_USER = "james"
DB = "james"

# Where to find the password.php file
DB_PWD_FILEPATH = '/home/james/mcrpwd.php'
PWD_LINE = "$pwd ="
TABLE_PREFIX = "mcrevents_"

# Logfile
LOGFILEPATH = '/home/james/Desktop/applications.log'
    
# Set up our logging machine
logging.basicConfig(filename=LOGFILEPATH,level=logging.DEBUG)

# Connection function
def get_db_password():
    db_pwd = ''

    # Collect the password for the claremcr mysql user from file

    pwfh = open(DB_PWD_FILEPATH, 'r')
    list = pwfh.readlines()
    pwfh.close()

    for x in list:
        if PWD_LINE in x:
            fields = x.split('=')
            db_pwd = fields[1].strip()
            db_pwd = db_pwd.strip('\';')
            return db_pwd
        else:
            pass

def dbconnect():
    """
    Connects to the database in question
    """
    try :
        db_pwd = get_db_password()
        db = MySQLdb.connect(host=DB_HOST,user=DB_USER,passwd=db_pwd,db=DB)
        return db.cursor()
    except MySQLdb.Error, e:
        logging.error("An error has occurred, database connection failed. %s" %(e))
        

cursor = dbconnect()

numberapps = sys.argv[1]

for i in range(int(numberapps)):
    
    # Which event?
    
    cursor.execute("SELECT id FROM mcrevents_eventslist")
    data = cursor.fetchall()
    
    chosen = choice(data)
    eventid = int(chosen[0])
    booker = random.getrandbits(10)
    
    hasher = hashlib.md5()
    hasher.update(str(booker))
    booker = hasher.hexdigest()[0:5]

    cursor.execute("SELECT max_guests FROM mcrevents_eventslist WHERE id=%s",(eventid))
    data = cursor.fetchone()
    
    maxtickets = data[0] + 1
    
    tickets = random.randint(1,maxtickets)
    
    cursor.execute("INSERT INTO mcrevents_queue (eventid,booker,admin,tickets) VALUES (%s,%s,0,%s)",(eventid,booker,tickets,))
    cursor.execute("SELECT LAST_INSERT_ID()")
    
    bookingid = cursor.fetchone()[0]
    
   
    for pos,ticket in enumerate(range(tickets)):
        if pos == 0:
            type = 1
        else:
            type = 0
        
        name = "AAAAAAAA"
        diet = choice(['None','Vegetarian','Vegan'])
        
        cursor.execute("INSERT INTO mcrevents_queue_details (bookingid,eventid,booker,admin,type,name,diet) VALUES (%s,%s,%s,%s,%s,%s,%s)",(bookingid,eventid,booker,0,type,name,diet,))
