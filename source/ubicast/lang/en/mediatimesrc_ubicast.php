<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     mediatimesrc_ubicast
 * @category    string
 * @copyright   2026 bdecent gmbh <https://bdecent.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apierror'] = 'UbiCast Nudgis request failed: {$a}';
$string['apikey'] = 'API key';
$string['apikey_help'] = 'API key of the Nudgis account used by Moodle. It is shown on the profile page of the account in Nudgis.';
$string['channel'] = 'Upload channel';
$string['channel_help'] = 'Object id of the Nudgis channel that receives videos uploaded from Moodle, for example c126d6380df2fs35wf44. The channel must be one that anonymous users may watch, because this source only supports public videos. Uploads are not possible until a public channel is set.';
$string['channelnotpublic'] = 'The upload channel set for the UbiCast Nudgis source is not public, so uploaded videos could not be played. Ask an administrator to choose a public channel.';
$string['confirmdeleteresource'] = 'Confirm that the resource below should be deleted.';
$string['credentialsnotconfigured'] = 'The UbiCast Nudgis URL and API key are not configured.';
$string['description'] = 'Description';
$string['description_help'] = 'This description is saved with the video on UbiCast Nudgis.';
$string['enabledraganddrop'] = 'Enable drag and drop';
$string['enabledraganddrop_help'] = 'Upload a video file to UbiCast Nudgis when a Video Time activity is created by dragging the file onto a course page, instead of storing the file in Moodle. A UbiCast Nudgis resource is also created in Media Time. Needs a public upload channel.';
$string['event_resource_created'] = 'UbiCast Nudgis resource created';
$string['event_resource_deleted'] = 'UbiCast Nudgis resource deleted';
$string['event_resource_updated'] = 'UbiCast Nudgis resource updated';
$string['file'] = 'Video';
$string['file_help'] = 'Choose an existing video from UbiCast Nudgis. The list shows the most recent videos; type to search by title, description, keywords or speaker, or paste the object identifier or link of a video. Only published videos that anonymous users may watch can be used.';
$string['filesource'] = 'File source';
$string['filesource_help'] = 'Choose an existing video on UbiCast Nudgis, or upload a new video file.';
$string['keepubicastfiles'] = 'Keep the video on UbiCast Nudgis';
$string['mediaid'] = 'Nudgis media id';
$string['nochannel'] = 'No upload channel is set for the UbiCast Nudgis source. Ask an administrator to set a public channel.';
$string['notpublic'] = 'This video is not public on UbiCast Nudgis. Only videos that anonymous users may watch can be used.';
$string['notpublished'] = 'This video is not published on UbiCast Nudgis.';
$string['pluginname'] = 'UbiCast Nudgis';
$string['privacy:metadata'] = 'The UbiCast Nudgis plugin for Media Time does not store personal data. It does transmit files associated with educational video content that normally does not contain user data. If it is used for personal data, a separate agreement with the provider may be required.';
$string['processingmessage'] = 'The video is not ready to display. UbiCast Nudgis is still processing it.';
$string['removeubicastfiles'] = 'Delete the video on UbiCast Nudgis too';
$string['selectexistingfile'] = 'Select existing video';
$string['ubicast:upload'] = 'Upload videos to UbiCast Nudgis';
$string['ubicast:viewall'] = 'Search all videos on UbiCast Nudgis';
$string['ubicastfileaction'] = 'UbiCast Nudgis video';
$string['ubicastfileaction_help'] = 'This video was uploaded from Moodle. Choose whether it is also deleted on UbiCast Nudgis, where it goes to the recycle bin. Other Media Time resources and Nudgis pages that use the video will no longer be able to play it.';
$string['updatemedia'] = 'Update UbiCast Nudgis videos';
$string['uploadnewfile'] = 'Upload new video';
$string['url'] = 'Nudgis URL';
$string['url_help'] = 'Address of the UbiCast Nudgis portal, for example https://bdecent.ubicast.tv';
$string['videofile'] = 'Video file';
$string['videofile_help'] = 'The file is uploaded from Moodle to UbiCast Nudgis when the form is saved. The video can be played after Nudgis has processed it.';
