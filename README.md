# CC Global Network

cc-global-network is a monolithic plugin that supports the registration and vouching functions that we need.

## Registration

cc-global-network removes those parts of the BuddyPress User Profile UI that
clash with the use of CAS for login - the ability to change the user email and
nickname, etc.

Registration forms are implemented using [GravityForms](https://www.gravityforms.com/).


## Vouching

cc-global-network uses WordPress's APIs to determine whether a user is logged
in, and if so whether they are an admin or not.

It uses its own database table to track how many vouches a user has received.
The user interface for vouching is implemented as a GravityForm.

cc-global-network uses Buddypress's APIs to control each user's access to
profile information based on whether they are logged in, vouched, or can vouch.

The user levels that result from this logic, and that the code considers, are:

* PUBLIC - The user is not logged in. Anything they can see can be seen by the
entire world.

* APPLICANT - The user is applying to become a member. They can see existing
members' basic profiles.

* VOUCHED - The user is vouched and approved as a full member. They can see
everyone's full profiles.

* ADMIN - The user is a WordPress Administrator.
