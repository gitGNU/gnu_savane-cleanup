<?php
# This file is part of the Savane project
# <http://gna.org/projects/savane/>
#
# $Id$
#
#  Copyright 1999-2000 (c) The SourceForge Crew
#  Copyright 2001-2002 (c) Laurent Julliard, CodeX Team, Xerox
#
#  Copyright 2002-2006 (c) Mathieu Roy <yeupou--gnu.org>
#                          Yves Perrin <yves.perrin--cern.ch>
#
#
# The Savane project is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# The Savane project is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with the Savane project; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

require_once(dirname(__FILE__).'/../trackers/votes.php');

# This page does not give access to sober mode
$sober = false;

# No group, no item was passed
if (!$group_id)
{
  exit_no_group();
}

# Get parameters
extract(sane_import('all',
  array('func', 'printer')));
extract(sane_import('post',
  array('form_id', # anti double-post
	// The comment/follow-up itself
	'comment', 'additional_comment', 'canned_response',
	'comment_type_id', # comment type
	// carbon-copy
	'add_cc', 'cc_comment',
	// vote field
	'new_vote',
	// Reassign item: search a project to assign the item to
	'depends_search',
	'depends_search_only_artifact', 'depends_search_only_project',
	'reassign_change_project_search', 'reassign_change_project',
	'reassign_change_artifact',
	'dependent_on_task', 'dependent_on_bugs', 'dependent_on_support', 'dependent_on_patch',
	// Second button 'submit but then edit this item again'
	'submitreturn'
	)));

# Other form fields: check trackers_extract_field_list()


// FIXME: quotation is broken with new markup feature
$change_quotation_style = null;

# Initialize the global data structure before anything else
trackers_init($group_id);

$project=project_get_object($group_id);
$changed = false;
$changes = array();

$browse_preamble = '';
$previous_form_bad_fields = false;
$sober = false;

$address = '';


$func = $func or 'browse';

switch ($func)
{
  
 case 'search' :
   {
# Form to do a search on the item database

     include '../include/trackers_run/search.php';
     break;
   }

 case 'digest' :
   {
# Form to create an item digest: search item stage

     include '../include/trackers_run/digest.php';
     include '../include/trackers_run/browse.php';	
     break;
   }

 case 'digestselectfield' :
   {
# Form to create an item digest: select field stage

     include '../include/trackers_run/digest.php';
     break;
   }

 case 'digestget' :
   {
# Form to create an item digest: output

     include '../include/trackers_run/digest.php';
     break;
   }

 case 'browse' :
   {
# Browse thru the bug database
# (it also the Default)

     include '../include/trackers_run/browse.php';
     break;
   }

 case 'additem' :
   {
# Form to add new item

     include '../include/trackers_run/add.php';
     break;
   }

 case 'detailitem' :
   {
### Show a bug already in the database, permitting to add comment
### or even modify.

     if (member_check(0,$group_id,member_create_tracker_flag(ARTIFACT).'2'))
       {
	 dbg("Management/Technician rights, include mod.php");
	 include '../include/trackers_run/mod.php';
       }
     else
       {
	 dbg("No specific rights, include detail.php");
	 include '../include/trackers_run/detail.php';
       }
     break;
   }

 case 'postadditem' :
   {
### Actually add in the database what was filled in the form

 if (!user_isloggedin() && ($_POST['check'] != 421))
 { exit_error(_("You're not logged in and you didn't enter the magic anti-spam number, please go back!")); }

     # Check for duplicates
     if (!form_check($form_id))
       { exit_error(_("Exiting")); }

     # Get the list of bug fields used in the form
     $vfl = trackers_extract_field_list();

     # Data control layer
     $item_id = trackers_data_create_item($group_id,$vfl,$address);

     if ($item_id)
       {

         # Attach new file if there is one
         # As we need to create the item first to have an item id so this
         # function can work properly, we wont be able to update the
         # comment on-the-fly to mention in the comment the attached files.
         # However, this is unlikely to be a problem because the attached
         # files is the section next to comments, and the original 
         # submission in the latest comment. So the proximity is always
         # optimal.
	 if (sane_upload("input_file1", "tmp_name") ||
	     sane_upload("input_file2", "tmp_name") ||
	     sane_upload("input_file3", "tmp_name") ||
	     sane_upload("input_file4", "tmp_name"))
	   {
             # (attach_several_files will use sane_() functions to get the
             # the necessary info)
	     list($changed,) = 
	       trackers_attach_several_files($item_id,
					     $group_id,
					     $changes);
	   }

         # Add new cc if any
	 if ($add_cc && user_isloggedin())
	   {
	     trackers_add_cc($item_id,
			     $group_id,
			     sane_post("add_cc"),
			     sane_post("cc_comment"), # 4
			     $changes);
	   }

         # Originator Email:
         # "Email address of the person who submitted the item
            # (if different from the submitter field, add address to CC list)"
         # Only apply this behavior if the field is present and used
	 $oe_field_name = "originator_email";

	 $is_trackeradmin = member_check(0,$group_id,member_create_tracker_flag(ARTIFACT).'2');

         # $oem=sane_post($oe_field_name);
         # $valoem=validate_email(sane_post($oe_field_name));
         # $used=trackers_data_is_used($oe_field_name);
         # print "OEM=$oem VALID=$valoem USED=$used ITA=$is_trackeradmin \n";

	 if (trackers_data_is_used($oe_field_name))
	   {
	     if  (($is_trackeradmin && trackers_data_is_showed_on_add_members($oe_field_name)) ||
		  (!$is_trackeradmin && trackers_data_is_showed_on_add($oe_field_name)) ||
		  (!user_isloggedin() && trackers_data_is_showed_on_add_nologin($oe_field_name))) 
	       {
                 # cannot be a registered user
		 if (validate_email(sane_post($oe_field_name)))
		   {
                  # must be different from the submitter field
		     $user=user_getid();
		     $submitter_email = db_result(db_query("SELECT email FROM user WHERE user_id=".$user),
						  0, 'email');
		     if (sane_post($oe_field_name) != $submitter_email)
		       {
			 trackers_add_cc($item_id,
					 $group_id,
					 sane_post($oe_field_name),
					 "-SUB-",
					 $changes);
		       }
		   }
		 else
		   {
		     $oem=sane_post($oe_field_name);
		     fb(_("Originator E-mail is not valid, thus was not added to the Carbon-Copy list."), 1);
		   }
	       }
	   }

         # Send an email to notify the user of the item update
         # (third arg of get_item_notification must be 0 for a first 
         # submission)
	 list($additional_address, $sendall) = trackers_data_get_item_notification_info($item_id, ARTIFACT, 0);
	    
	 if ((trim($address) != "") && (trim($additional_address) != "")) 
	   {
	     $address .= ", ";
	   }
	 $address .= $additional_address;

	 trackers_mail_followup($item_id, $address);

       }
     else
       {
         # Some error occurred  

         # Missing mandatory field?
         # The relevant error message was supposedly properly produced by
         # trackers_data_create_item.
         # Reshow the same page
	 if ($previous_form_bad_fields)
	   {		   
             # Mention if there was an attached file: we cannot
             # pre-fill an HTML input file. 
	     for ($file = 1; $file < 5; $file++)
	       {
		 if (sane_upload("input_file$file", "tmp_name"))
		   {
		     fb(sprintf(_("Warning: do not forget to re-attach your file '%s'"), sane_upload("input_file$file", "name", 1)));
			
		   }
	       }
		
	     include '../include/trackers_run/add.php';
	     break;
	   }

         # Otherwise, that's odd and there's not much to do. 
	 fb(_("Missing parameters, nothing added."), 1);

       }

     # show browse item page
     include '../include/trackers_run/browse.php';
     break;
   }

 case 'postmoditem' :
   {

### Actually add in the database what was filled in the form
### for a bug already in the database, reserved to item techn.
### or manager.

     # Check for duplicates
     if (!form_check($form_id))
       { exit_error(_("Exiting")); }

     # Check if the submitter is manager or technician
     if (!member_check(0,$group_id,member_create_tracker_flag(ARTIFACT).'2'))
       { exit_permission_denied(); }

     dbg("Techn. or Manager rights, make an update on almost every fields.");
     
     # To keep track of changes
     $changes = array();
     
     # Special case: we may be searching for an item, in that case
     # reprint the same page, plus search results.
     if ($depends_search || 
	 $reassign_change_project_search ||
	 $canned_response == "!multiple!" ||
	 $change_quotation_style)
       {
	 if ($depends_search)
	   {
	     fb(sprintf(_("You provided search words to get a list of items this one may depend on. Below, in the section [%s Dependencies], you can now select the appropriate one(s) and submit the form."), $GLOBALS['sys_https_url'].$_SERVER['PHP_SELF'].'#dependencies'));
	   }
	 if ($reassign_change_project_search)
	   {
	     fb(sprintf(_("You provided search words to get a list of projects this item should maybe reassigned to. Below, in the section [%s Reassign this item], you can now select the appropriate project and submit the form."), $GLOBALS['sys_https_url'].$_SERVER['PHP_SELF'].'#reassign'));
	   }
	 if ($canned_response == "!multiple!")
	   {
	     fb(_("You selected Multiple Canned Responses: you are free now to select the one you want to use to compose your answer."));
	   }
	 if ($change_quotation_style)
	   {
	     if ($change_quotation_style == _("Quoted, ready to be copied/pasted into your new comment")) 
	       {
		 $quotation_style = "quoted";
		 fb(_("Previous comments will now be printed in a copy/paste-friendly mode."));
	       }
	     else
	       {
		 $quotation_style = false;
	       }
	   }
	 
	 include '../include/trackers_run/mod.php';
	 break;
       }
	    
     # Get the list of bug fields used in the form
     $vfl = trackers_extract_field_list();

     # Attach new file if there is one
     # Do that first so it can update the comment
     if (sane_upload("input_file1", "tmp_name") ||
	 sane_upload("input_file2", "tmp_name") ||
	 sane_upload("input_file3", "tmp_name") ||
	 sane_upload("input_file4", "tmp_name"))
       {
         # (attach_several_files will use sane_() functions to get the
         # the necessary info)
	 list($changed, $additional_comment) = 
	   trackers_attach_several_files($item_id,
					 $group_id,
					 $changes);

             # If there is an item for this comment, add the additional
             # comment providing refs to the item
	 if (array_key_exists('comment', $vfl) && 
	     $vfl['comment'] != '')
	   {
	     $vfl['comment'] .= $additional_comment;
	   }
       }

     # data control layer
     $changed |= trackers_data_handle_update($group_id,
					     $item_id,
					     $dependent_on_task,
					     $dependent_on_bugs, # 4
					     $dependent_on_support,
					     $dependent_on_patch, # 6
					     $canned_response,
					     $vfl, # 8
					     $changes,
					     $address);
     
     # The update failed due to a missing field? Reprint it and squish
     # the rest of the action normally done
     if (!$changed && $previous_form_bad_fields)
       {
         # Mention if there was an attached file: we cannot
         # pre-fill an HTML input file.
	 for ($file = 1; $file < 5; $file++)
	   {
	     if (sane_upload("input_file$file", "tmp_name"))
	       {
		 fb(sprintf(_("Warning: do not forget to re-attach your file '%s'"), sane_upload("input_file$file", "name", 1)));
		 
	       }
	   }
	 
	 include '../include/trackers_run/mod.php';
	 break;
       }

     # Add new cc if any
     if ($add_cc)
       {
         # No notification needs to be sent when a cc is added,
         # it is irrelevant to the item itself
	 trackers_add_cc($item_id,
			 $group_id,
			 sane_post("add_cc"),
			 sane_post("cc_comment"), # 4
			 sane_post("changes"));
       }

     # Update vote (will do the necessary checks itself)
     # Currently votes does not influence notifications
     # (that could harass developers)
     if (trackers_data_is_used("vote")) 
       {
	 trackers_votes_update($item_id,
			       $group_id,
			       sane_post("new_vote"));		      
       }
	    	    	    
     # Now handle notification, after all necessary actions has been 
     if ($changed)
       {
         # Check if we re supposed to send all modifications to an address
	 list($additional_address, $sendall) = trackers_data_get_item_notification_info($item_id, ARTIFACT, 1);
	 
	 if (($sendall == 1) && (trim($address) != "") && (trim($additional_address) != "")) 
	   {
	     $address .= ", ";
	   }
	 $address .= $additional_address;
	 trackers_mail_followup($item_id, $address,$changes);
	    
         # If the assigned_to was changed and the previously assigned 
         # guy
         # wants to be removed from CC when he is no longer assigned,
         # do it now.
         # We do this after the item update so the previously assignee
         # got the notification of the this change.
	 if (!empty($changes['assigned_to']['del']))
	   {
	     $previously_assigned_uid = user_getid($changes['assigned_to']['del']);
	     if (user_get_preference("removecc_notassignee",
				     $previously_assigned_uid))
	       {
                 # No feedback for this
		 trackers_delete_cc_by_user($item_id, $previously_assigned_uid);
	       }
	   }
	 
       }
	    
     
     # Handle reassignation of an entry. Why so late?
     # Because all the information entered by someone reassigning
     # the bug must be in the original report, and will be duplicated
     # in the new one.
     if ($reassign_change_project || ($reassign_change_artifact && ($reassign_change_artifact != ARTIFACT)))
       {
	 dbg("reassign item: reassign_change_project:$reassign_change_project, reassign_change_artifact:$reassign_change_artifact, ARTIFACT:".ARTIFACT);
	 trackers_data_reassign_item($item_id,
				     sane_post("reassign_change_project"),
				     sane_post("reassign_change_artifact"));
       }
     

	    
     # show browse item page, unless the user want to get back
     # to the 
     # same report, to make something else
     if (!$submitreturn) 
       {
	 include '../include/trackers_run/browse.php';
       }
     else
       {
	 $_POST = $_FILES = array();
	 $form_id = $depends_search = $reassign_change_project_search = $add_cc
	   = $input_file = $changed = $vfl = $details = $comment = null;
	 $nocache = 1;
	 include '../include/trackers_run/mod.php';
       }
     
     break;
   }
      
 case 'postaddcomment' :
   {
 if (!user_isloggedin() && ($_POST['check'] != 421))
 { exit_error(_("You're not logged in and you didn't enter the magic anti-spam number, please go back!")); }

### Add a comment to a bug already in the database,
### these are the only changes an non member can make
     $changed = false;

     # Check for duplicates
     if (!form_check($form_id))
       { exit_error(_("Exiting")); }

      # Check restrictions: should never end up here, if so, assume 
      # it is malicious
     if (!group_restrictions_check($group_id, ARTIFACT, 2))
       { exit_permission_denied(); }

     # Filter out people that would submit data while they are not allowed 
     # too (obviously by using an old form, or something else)
     $sql="SELECT privacy,discussion_lock,submitted_by FROM ".ARTIFACT." WHERE bug_id='$item_id' AND group_id='$group_id'";
     $result=db_query($sql);
	
     if (db_numrows($result) > 0) 
       {
         # Check if the item is private, refuse post if it is and the 
         # users has no appropriate rights (not member, not submitter)
	 if (db_result($result,0,'privacy') == '2')
	   {
	     if (!member_check(user_getid(), $group_id) &&
		 db_result($result,0,'submitted_by') != user_getid())
	       {
		 # As the user here is expected to behave maliciously,
		 # return an error message that does not give too much info
		 exit_permission_denied();
		 #exit_error(_("This item is private."));
	       }
	   }

	 # Exit if the discussion is locked
	 if (db_result($result,0,'discussion_lock'))
	   {
	     exit_permission_denied();
	   }	 
       }	
     else
       {
	 # Nothing found? Something obviously weird!
	 exit_permission_denied();
       }
		    
     # To keep track of changes
     $changes = array();

     # Attach new file if there is one
     # Do that first so it can update the comment
     $additional_comment = '';
     if (sane_upload("input_file1", "tmp_name") ||
	 sane_upload("input_file2", "tmp_name") ||
	 sane_upload("input_file3", "tmp_name") ||
	 sane_upload("input_file4", "tmp_name"))
       {
         # (attach_several_files will use sane_() functions to get the
         # the necessary info)
	 list($changed, $additional_comment) = 
	   trackers_attach_several_files($item_id,
					 $group_id,
					 $changes);
       }

		    
     # Add a new comment if there is one
     if ($comment != '')
       {
         # Add the additionnal comment that may have been added during
         # the file upload
	 $comment .= $additional_comment;

         # For none project members force the comment type to None (100)
	 # The delay for spamcheck will be called from this function:
	 trackers_data_add_history('details',htmlspecialchars($comment),'',$item_id,100);

         # YPE fix to trigger notifications in case of non member
	 $changes['details']['add'] = stripslashes($comment);
	 $changes['details']['type'] = 'None';
	 $changed = true;
	 
         # Add to CC list unless prefs says not to
         # (usually, this part is handled directly in functions included
         # in general.php, but here as we do a direct insert, we need
         # to also do this now)
	 if (user_isloggedin() && 
	     !user_get_preference("skipcc_postcomment"))
	   {
	     trackers_add_cc($item_id,
			     $group_id,
			     user_getid(),
			     "-COM-");
                  # use a flag as comment, because if we 
                  # translate the string now, people will get
                  # the translation of the submitter when they
                  # read the item, not necessarily the one they
                  # want
	   }

	 fb(_("Comment added"));
       }
	
     # Add new cc if any, only accepted from logged in users.
     if ($add_cc && user_isloggedin())
       {
         # No notification needs to be sent when a cc is added,
         # it is irrelevant to the item itself
	 trackers_add_cc($item_id,
			 $group_id,
			 sane_post("add_cc"),
			 sane_post("cc_comment"), # 4
			 $changes);
       }
	
     # Add vote, if configured to be accepted from non members or if 
     # the user is member
     if (trackers_data_is_used("vote"))
       {
	 if (trackers_data_is_showed_on_add("vote") && user_isloggedin() ||
	     member_check(user_getid(), $group_id))
	   {
             # Currently votes does not influence notifications
             # (that could harass developers)
	     trackers_votes_update($item_id,
				   $group_id,
				   sane_post("new_vote"));	
	   }
       }
     if ($changed)
       {
	 list($additional_address, $sendall) = trackers_data_get_item_notification_info($item_id, ARTIFACT, 1);
	 if (($sendall == 1) && (trim($address) != "") && (trim($additional_address) != "")) 
	   {
	     $address .= ", ";
	   }
	 $address .= $additional_address;
	 trackers_mail_followup($item_id, $address, $changes);
       }
	    
	
     include '../include/trackers_run/browse.php';
     break;
   }

 case 'delete_file' :
   {
# Remove an attached file

     if (member_check(0,$group_id, member_create_tracker_flag(ARTIFACT).'2'))
       {
	 trackers_data_delete_file($group_id,
				   $item_id,
				   sane_all("item_file_id"));

# unset previous settings and return to the item
	 $depends_search = $reassign_change_project_search = $add_cc
	   = $input_file = $changed = $vfl = $details = null;
	 include '../include/trackers_run/mod.php';
       }
     else
       {
	 exit_permission_denied();
       }
     break;
   }

 case 'delete_cc' :
   {
#### Remove a person from the Cc
     $changed = trackers_delete_cc($group_id,
				   $item_id,
				   sane_all("item_cc_id"),
				   $changes);

# Irrevelant: no need to warn people that someone got removed from the
# cc list.
#	if ($changed)
#	  {
# see if we are supposed to send all modifications to an address
#	     list($additional_address, $sendall) = trackers_data_get_item_notification_info($item_id, ARTIFACT, 1);
#	     if (($sendall == 1) && (trim($address) != "") && (trim($additional_address) != "")) 
#{
#	        $address .= ", ";
#             }
#	     $address .= $additional_address;
#             trackers_mail_followup($item_id, $address,$changes);
#	    }

# unset previous settings and return to the item
     $depends_search = $reassign_change_project_search = $add_cc = $input_file
       = $changed = $vfl = $details = null;
         
# CC may be deleted by a user without privilegies, if it is himself
     if (member_check(0,$group_id,member_create_tracker_flag(ARTIFACT).'2'))
       {
	 dbg("Management/Technician rights, include mod.php");
	 include '../include/trackers_run/mod.php';
       }
     else
       {
	 dbg("No specific rights, include detail.php");
	 include '../include/trackers_run/detail.php';
       }

     break;
   }
      
 case 'delete_dependancy' :
   {
### Remove a dependancy

     $changed |= trackers_delete_dependancy($group_id,
					    $item_id,
					    sane_all("item_depends_on"),
					    sane_all("item_depends_on_artifact"),
					    $changes);
	
     if ($changed)
       {
# see if we are supposed to send all modifications to an address
	 list($additional_address, $sendall) = trackers_data_get_item_notification_info($item_id, ARTIFACT, 1);
	 if (($sendall == 1) && (trim($address) != "") && (trim($additional_address) != "")) 
	   {
	     $address .= ", ";
	   }
	 $address .= $additional_address;
	 trackers_mail_followup($item_id, $address,$changes);
       }


# unset previous settings and return to the item
     $depends_search = $reassign_change_project_search = $add_cc = $input_file
       = $changed = $vfl = $details = $changes = $address = null;
     include '../include/trackers_run/mod.php';
	
     break;
   }
      	
 case 'flagspam' :
   {
## Report a spam
	
# Only allowed to logged in user
     if (!user_isloggedin())
       { 
         # Do not use exit_not_logged_in(), because the user has no
         # valid reason to get here if he was not logged in in first place
         # (the link was not provided)
	 exit_permission_denied();
       }

     # Determine the additional spamscore according to user credentials
     # +1 = logged in user
     # +3 = project member
     # +5 = project admin
     $spamscore = 1;
     if (member_check(0, $group_id))
       {
	 if (member_check(0, $group_id, 'A'))
	   { $spamscore = 5; }
	 else
	   { $spamscore = 3; }
	    
       }

     spam_flag($item_id, 
	       sane_get("comment_internal_id"), 
	       $spamscore,
	       $group_id);
	
	

     # Return to the item page if it was not the item itself that was
     # marked as spam
     if (member_check(0,$group_id,member_create_tracker_flag(ARTIFACT).'2'))
       {
	 dbg("Management/Technician rights, include mod.php");
	 include '../include/trackers_run/mod.php';
       }
     else
       {
	 dbg("No specific rights, include detail.php");
	 include '../include/trackers_run/detail.php';
       }

     break;
   }

 case 'unflagspam' :
   {
## Unflag an alledged spam: for projects admins only
#
     if (!member_check(0, $group_id, 'A'))
       { 
         # Do not use exit_not_logged_in(), because the user has no
         # valid reason to get here if he was not logged in in first place
         # (the link was not provided)
	 exit_permission_denied();
       }
	
     spam_unflag($item_id, 
		 sane_get("comment_internal_id"),
		 ARTIFACT,
		 $group_id);

     include '../include/trackers_run/mod.php';
     break;
   }


 case 'viewspam' :
   {
     if (member_check(0,$group_id,member_create_tracker_flag(ARTIFACT).'2'))
       {
	 dbg("Management/Technician rights, include mod.php");
	 include '../include/trackers_run/mod.php';
       }
     else
       {
	 dbg("No specific rights, include detail.php");
	 include '../include/trackers_run/detail.php';
       }
	
     break;
   }
  
   /*
         Follow the code of filters (query form) modification.
         This is currently broken, due to the separation of personal
         and project-wide configuration.

         It will be reactivate, but maybe not in this index page.

    case 'modfilters' :
      {
      # Modification of the filters (query form)

	if (user_isloggedin())
	  {
	    include '../include/trackers_run/mod_filters.php';
	    break;
	  }
	else
	  { exit_not_logged_in(); }
      }

    case 'postmodfilters' :
      {
	if (user_isloggedin())
	  {
	    include '../include/trackers_run/postmod_filters.php';
	    include '../include/trackers_run/mod_filters.php';
	    break;
	  }
	else
	  { exit_not_logged_in(); }
      }
   */

 case 'browse' :      
 default :
   {
### Browse thru the bug database

     include '../include/trackers_run/browse.php';
     break;
   }

}


?>
