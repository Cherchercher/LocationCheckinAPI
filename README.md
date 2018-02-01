Location check-in API
===============

_Author: Cher Huang (<xiaoxuah@uci.edu>)_

This application is a simple check-in service that manages user check-ins in places around the world. It has two object types: users and check-ins. Each user has basic attributes such as first name, last name, email address, based-location, current-location, estimated spending in traveling each year, and a unique ID. Also, each user can have a number of places that he/she has checked-in to. Each place has a unique ID, a good-for tag, and a location. 

Characteristics of this API:
1)	User Friendly 
2)	RESFUL 
3)	Secured: the API  will be accessible only in HTTPS mode 

## Resources and Actions

URL                HTTP         Method          Operation
/api/users 			            GET          Returns an array of users
/api/users/:id                  GET          Returns the user with id of :id
/api/users                	    POST         Adds a new user and return it with an id attribute added
/api/users/:id            	    PUT          Updates the user with id of :id
/api/users/:id             	    PATCH        Partially updates the user with id of :id
/api/users/:id                  DELETE       Deletes the user with id of :id


/api/users/:id/places	        GET          Returns places checked-in to for the user with id of :id
/api/users/:id/places /:pid     GET          Returns the place with id of :nid for the user with id of :id
/api/users/:id/places            POST         Adds a new place for the user with id of :id
/api/users/:id/places/:pid      PUT          Updates the place with id if :pid for the user with id of :id
/api/users/:id/places/:pid      PATCH        Partially updates the place with id of :pid for the user with id of :id
/api/users/:id/places/:pid   DELETE       Deletes the place with id of :pid for the user with id of :id

