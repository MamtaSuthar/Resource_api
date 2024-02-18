import fitz
import mysql.connector
import sys
import os
import logging
from dotenv import load_dotenv
import re

load_dotenv( os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../.env')) )

# change me
siteUrl = 'https://graphicnewsplus.com/modal-post?post-id='

logging.basicConfig( filename = 'logs.txt',filemode = 'a',level = logging.DEBUG,format = '%(asctime)s - %(levelname)s: %(message)s', datefmt = '%m/%d/%Y %I:%M:%S %p' )

try:
        
    if( len(sys.argv) < 2 ):
        raise Exception("File arg not passed")
    file_path = sys.argv[1]
    publishing_date = sys.argv[2] # Y-m-d

    if( not os.path.isfile(file_path) ):
        raise Exception("File does not exist: " + str(file_path))

    file_dir  = os.path.dirname(file_path)
    file_name = os.path.basename(file_path)

    dataBase = mysql.connector.connect(
      host=os.environ.get('DB_HOST'),
      user=os.environ.get('DB_USERNAME'),
      passwd=os.environ.get('DB_PASSWORD'),
      database=os.environ.get('DB_DATABASE')
    )

    cursorObject = dataBase.cursor()

    # Get blogs of last 1 day
    # 0 = id, 1 = title
    if( publishing_date ):
        cursorObject.execute('SELECT `id`,`title` FROM `gcgl_blogs` WHERE DATE(`created_at`) BETWEEN DATE_SUB("'+str(publishing_date)+'", INTERVAL 1 DAY) AND "'+str(publishing_date)+'"')
    else:
        cursorObject.execute("SELECT `id`,`title` FROM `gcgl_blogs` WHERE DATE(`created_at`) >= (CURDATE() - INTERVAL 1 DAY)")

    posts = cursorObject.fetchall()

    doc = fitz.open(file_path)  # open document

    for page in doc:  # iterate the document pages
        # Get all the text on the page
        # text_byte = page.get_text() # get plain text (is in UTF-8)
        for post in posts:
            title = post[1].strip()

            # areas = page.search_for(title) # get areas of the places where headlines are found
            # if len(areas) != 0:
            #     for j in areas:
            #         page.insert_link({'kind': 2, 'from': j, 'uri': siteUrl+str(post[0])}) # insert links wherever the headline was found

             # Check if any case of comma is there in article title
            if re.search("'|’", title) is not None:
                title1 = re.sub('[\'\’]', '’', title) # Make normalizations for both cases
                title2 = re.sub('[\'\’]', "'", title)
                #print(title1, title2)

                # Search for both normalized cases
                areas1 = page.search_for(title1) # get areas of the places where headlines are found
                if len(areas1) != 0:
                    for j in areas1:
                        page.insert_link({'kind': 2, 'from': j, 'uri': siteUrl+str(post[0])}) # insert links wherever the headline was found
                        
                areas2 = page.search_for(title2) # get areas of the places where headlines are found
                if len(areas2) != 0:
                    for j in areas2:
                        page.insert_link({'kind': 2, 'from': j, 'uri': siteUrl+str(post[0])}) # insert links wherever the headline was found
            else:
                areas = page.search_for(title) # get areas of the places where headlines are found
                if len(areas) != 0:
                    for j in areas:
                        page.insert_link({'kind': 2, 'from': j, 'uri': siteUrl+str(post[0])}) # insert links wherever the headline was found

    # filename = os.path.join(file_dir, file_name)
    doc.saveIncr()

except Exception as e:
    logging.debug(e)
    print(0)
    exit()

dataBase.close()

print(1)
exit()