<?php

/**
 * Playlist API.
 *
 * Provides the current user's playlist and the included tracks
 *
 * @version 1.1.0
 *
 * @api
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/Api.php';
$api = new Api('json', ['POST', 'GET', 'DELETE', 'PUT', 'PATCH']);
require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/Playlist.php';
switch ($api->method) {
    case 'GET':
        //querying a user playlist
        if (!$api->checkAuth()) {
            //User not authentified/authorized
            return false;
        }
        if (!$api->checkParameterExists('userId', $userId)) {
            $api->output(400, 'User identifier must be provided');
            //user was not provided, return an error
            return;
        }
        $userId = intval($userId);
        if ($api->requesterId !== $userId) {
            $api->output(403, 'Playlist can be queried by its owner only');
            //indicate the requester is not the playlist owner and is not allowed to get it
            return;
        }
        $playlist = new Playlist($userId);
        $playlist->populate();
        if (count($playlist->tracks) === 0) {
            $api->output(204, null);
            //user's playlist is empty
            return;
        }
        $api->output(200, $playlist->tracks);
        break;
    case 'POST':
        if (!$api->checkAuth()) {
            //User not authentified/authorized
            return false;
        }
        if (!$api->checkParameterExists('userId', $userId)) {
            $api->output(400, 'User identifier must be provided');
            //user was not provided, return an error
            return;
        }
        $userId = intval($userId);
        if ($api->requesterId !== $userId) {
            $api->output(403, 'Playlist can be updated by its owner only');
            //indicate the requester is not the playlist owner and is not allowed to update it
            return;
        }
        if (!$api->checkParameterExists('id', $trackId)) {
            $api->output(400, 'Track identifier must be provided');
            //track identifier was not provided, return an error
            return;
        }
        $playlistItem = new PlaylistItem($userId, null, $trackId);
        $response = $playlistItem->insert();
        if (!$response) {
            $api->output(500, 'Add error');
            //something happened during track insertion, return internal error
            return;
        }
        $api->output(201, $response);
        break;
    case 'DELETE':
        if (!$api->checkAuth()) {
            //User not authentified/authorized
            return false;
        }
        if (!$api->checkParameterExists('userId', $userId)) {
            $api->output(400, 'User identifier must be provided');
            //user was not provided, return an error
            return;
        }
        $userId = intval($userId);
        if ($api->requesterId !== $userId) {
            $api->output(403, 'Playlist can be updated by its owner only');
            //indicate the requester is not the playlist owner and is not allowed to update it
            return;
        }
        if (!$api->checkParameterExists('sequence', $sequence) || $sequence === '') {
            //$sequence was not provided, clear the playlist
            $playlist = new Playlist($userId);
            if (!$playlist->clear()) {
                $api->output(500, 'Clear error');
                //something happened during tracks deletion, return error
                return;
            }
            $api->output(204, null);
            //return the playlist cleared
            return;
        }
        $playlistItem = new PlaylistItem($userId, $sequence, null);
        if (!$playlistItem->delete()) {
            $api->output(404, 'No such track in playlist');
            //something happened during track deletion (probably sequence was not existing), return not found error
            return;
        }
        $api->output(204, null);
        break;
    case 'PUT':
        if (!$api->checkAuth()) {
            //User not authentified/authorized
            return false;
        }
        if (!$api->checkParameterExists('userId', $userId)) {
            $api->output(400, 'User identifier not provided');
            //user was not provided, return an error
            return;
        }
        $userId = intval($userId);
        if ($api->requesterId !== $userId) {
            $api->output(403, 'Playlist can be updated by its owner only');
            //indicate the requester is not the playlist owner and is not allowed to update it
            return;
        }
        if (!$api->checkParameterExists('newSequence', $newSequence)) {
            $api->output(400, 'New sequence not provided');
            //new sequence was not provided, return an error
            return;
        }
        if (!$api->checkParameterExists('sequence', $oldSequence)) {
            $api->output(400, 'Current sequence not provided');
            //old sequence was not provided, return an error
            return;
        }
        $playlist = new Playlist($userId);
        if (!$playlist->reorder($oldSequence, $newSequence)) {
            $api->output(500, 'Internal error');
            //something gone wrong :(
            return;
        }
        $playlist->populate();
        if (count($playlist->tracks) === 0) {
            $api->output(204, null);
            //user's playlist is empty (should not happens but we handle it)
            return;
        }
        //return all the user's playlist tracks for synchronizing with GUI
        $api->output(200, $playlist->tracks);
        break;
    case 'PATCH':
        if (!$api->checkAuth()) {
            //User not authentified/authorized
            return false;
        }
        if (!$api->checkParameterExists('userId', $userId)) {
            $api->output(400, 'User identifier must be provided');
            //user was not provided, return an error
            return;
        }
        $userId = intval($userId);
        if ($api->requesterId !== $userId) {
            $api->output(403, 'Playlist can be updated by its owner only');
            //indicate the requester is not the playlist owner and is not allowed to update it
            return;
        }
        //get request's body containing array of track's id
        if (!array_key_exists('body', $api->query)) {
            $api->output(400, 'Track identifiers must be provided in request body');
            //tracks identifier were not provided, return an error
            return;
        }
        $tracks = $api->query['body'];
        if (!is_array($tracks)) {
            $api->output(400, 'Track identifiers must be provided in an array');
            //tracks identifier were not provided, return an error
            return;
        }
        //add each tracks
        foreach ($tracks as $track) {
            $playlistItem = new PlaylistItem($userId, null, $track->id);
            $playlistItem->insert();
        }
        //get full user's playlist for response
        $playlist = new Playlist($userId);
        $playlist->populate();
        if (count($playlist->tracks) === 0) {
            $api->output(204, null);
            //user's playlist is empty
            return;
        }
        $api->output(200, $playlist->tracks);
        break;
}
