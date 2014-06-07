#!/bin/bash

# Group
php index.php group $group_id $mybb_forum_id
php index.php comment $group_id $mybb_forum_id
php index.php group-doc $group_id $mybb_forum_id

# Page
php index.php page $group_id $mybb_forum_id
php index.php comment $group_id $mybb_forum_id