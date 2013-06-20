{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}

{capture assign=volunteerNeedsURL}{crmURL p="civicrm/volunteer/need" q="reset=1&action=add&entityId=`$id`&entityTable=event"}{/capture}
<tr><td><a accesskey="N" href="{$volunteerNeedsURL}" class="button"><span><div class="icon edit-icon"></div>{ts}Define Volunteer Needs{/ts}</span></a></td><td></td></tr>

{capture assign=assignVolunteerURL}{crmURL p="civicrm/volunteer/assign" q="reset=1&action=add&entityId=`$id`&entityTable=event"}{/capture}
<tr><td><a accesskey="N" href="{$assignVolunteerURL}" class="button"><span><div class="icon edit-icon"></div>{ts}Assign Volunteers{/ts}</span></a></td><td></td></tr>

{capture assign=volunteerLogURL}{crmURL p="civicrm/volunteer/loghours" q="reset=1&action=add&entityId=`$id`&entityTable=event"}{/capture}
<tr><td><a accesskey="N" href="{$volunteerLogURL}" class="button"><span><div class="icon edit-icon"></div>{ts}Log Volunteer Hours{/ts}</span></a></td><td></td></tr>



