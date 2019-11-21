Introduction
============
Softcourse is a course format for Moodle. Softcourse is a course format that display sections with only an image and an optional text on the course homepage.

Required version of Moodle
==========================
This version works with Moodle version 2018050800 and above within the 3.5 branch until the
next release.

Please ensure that your hardware and software complies with 'Requirements' in 'Installing Moodle' on
'docs.moodle.org/35/en/Installing_Moodle'.

Installation
============
 1. Ensure you have the version of Moodle as stated above in 'Required version of Moodle'.  This is essential as the
    format relies on underlying core code that is out of our control.
 2. Put Moodle in 'Maintenance Mode' (docs.moodle.org/en/admin/setting/maintenancemode) so that there are no 
    users using it bar you as the administrator - if you have not already done so.
 3. Copy 'softcourse' to '/course/format/' if you have not already done so.
 4. Login as an administrator and follow standard the 'plugin' update notification.  If needed, go to
    'Site administration' -> 'Notifications' if this does not happen.
 5.  Put Moodle out of Maintenance Mode.

Upgrade Instructions
====================
 1. Ensure you have the version of Moodle as stated above in 'Required version of Moodle'.  This is essential as the
    format relies on underlying core code that is out of my control.
 2. Put Moodle in 'Maintenance Mode' so that there are no users using it bar you as the administrator.
 3. In '/course/format/' move old 'softcourse' directory to a backup folder outside of Moodle.
 4. Follow installation instructions above.
 5. If automatic 'Purge all caches' appears not to work by lack of display etc. then perform a manual 'Purge all caches'
    under 'Home -> Site administration -> Development -> Purge all caches'.
 6. Put Moodle out of Maintenance Mode.

Uninstallation
==============
 1. Put Moodle in 'Maintenance Mode' so that there are no users using it bar you as the administrator.
 2. It is recommended but not essential to change all of the courses that use the format to another.  If this is
    not done Moodle will pick the last format in your list of formats to use but display in 'Edit settings' of the
    course the first format in the list.  You can then set the desired format.
 3. In '/course/format/' remove the folder 'softcourse'.
 5. Put Moodle out of Maintenance Mode.

Version Information
===================
See Changes.md.


Us
==
Pimenko Team

