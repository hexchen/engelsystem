<?php

use Engelsystem\Database\DB;

/**
 * @return string
 */
function myshifts_title()
{
    return _('My shifts');
}

/**
 * Zeigt die Schichten an, die ein Benutzer belegt
 *
 * @return string
 */
function user_myshifts()
{
    global $user, $privileges;

    if (
        isset($_REQUEST['id'])
        && in_array('user_shifts_admin', $privileges)
        && preg_match('/^\d{1,}$/', $_REQUEST['id'])
        && count(DB::select('SELECT `UID` FROM `User` WHERE `UID`=?', [$_REQUEST['id']])) > 0
    ) {
        $user_id = $_REQUEST['id'];
    } else {
        $user_id = $user['UID'];
    }

    $shifts_user = DB::select('SELECT * FROM `User` WHERE `UID`=? LIMIT 1', [$user_id]);
    $shifts_user = array_shift($shifts_user);

    if (isset($_REQUEST['reset'])) {
        if ($_REQUEST['reset'] == 'ack') {
            User_reset_api_key($user);
            success(_('Key changed.'));
            redirect(page_link_to('users') . '&action=view&user_id=' . $shifts_user['UID']);
        }
        return page_with_title(_('Reset API key'), [
            error(
                _('If you reset the key, the url to your iCal- and JSON-export and your atom feed changes! You have to update it in every application using one of these exports.'),
                true
            ),
            button(page_link_to('user_myshifts') . '&reset=ack', _('Continue'), 'btn-danger')
        ]);
    } elseif (isset($_REQUEST['edit']) && preg_match('/^\d*$/', $_REQUEST['edit'])) {
        $user_id = $_REQUEST['edit'];
        $shift = DB::select('
                SELECT
                    `ShiftEntry`.`freeloaded`,
                    `ShiftEntry`.`freeload_comment`,
                    `ShiftEntry`.`Comment`,
                    `ShiftEntry`.`UID`,
                    `ShiftTypes`.`name`,
                    `Shifts`.*,
                    `Room`.`Name`,
                    `AngelTypes`.`name` AS `angel_type`
                FROM `ShiftEntry`
                JOIN `AngelTypes` ON (`ShiftEntry`.`TID` = `AngelTypes`.`id`)
                JOIN `Shifts` ON (`ShiftEntry`.`SID` = `Shifts`.`SID`)
                JOIN `ShiftTypes` ON (`ShiftTypes`.`id` = `Shifts`.`shifttype_id`)
                JOIN `Room` ON (`Shifts`.`RID` = `Room`.`RID`)
                WHERE `ShiftEntry`.`id`=?
                AND `UID`=?
                LIMIT 1
            ',
            [
                $user_id,
                $shifts_user['UID'],
            ]
        );
        if (count($shift) > 0) {
            $shift = array_shift($shift);
            $freeloaded = $shift['freeloaded'];
            $freeload_comment = $shift['freeload_comment'];

            if (isset($_REQUEST['submit'])) {
                $valid = true;
                if (in_array('user_shifts_admin', $privileges)) {
                    $freeloaded = isset($_REQUEST['freeloaded']);
                    $freeload_comment = strip_request_item_nl('freeload_comment');
                    if ($freeloaded && $freeload_comment == '') {
                        $valid = false;
                        error(_('Please enter a freeload comment!'));
                    }
                }

                $comment = strip_request_item_nl('comment');
                $user_source = User($shift['UID']);

                if ($valid) {
                    $result = ShiftEntry_update([
                        'id'               => $user_id,
                        'Comment'          => $comment,
                        'freeloaded'       => $freeloaded,
                        'freeload_comment' => $freeload_comment
                    ]);
                    if ($result === false) {
                        engelsystem_error('Unable to update shift entry.');
                    }

                    engelsystem_log(
                        'Updated ' . User_Nick_render($user_source) . '\'s shift ' . $shift['name']
                        . ' from ' . date('Y-m-d H:i', $shift['start'])
                        . ' to ' . date('Y-m-d H:i', $shift['end'])
                        . ' with comment ' . $comment
                        . '. Freeloaded: ' . ($freeloaded ? 'YES Comment: ' . $freeload_comment : 'NO')
                    );
                    success(_('Shift saved.'));
                    redirect(page_link_to('users') . '&action=view&user_id=' . $shifts_user['UID']);
                }
            }

            return ShiftEntry_edit_view(
                User_Nick_render($shifts_user),
                date('Y-m-d H:i', $shift['start']) . ', ' . shift_length($shift),
                $shift['Name'],
                $shift['name'],
                $shift['angel_type'],
                $shift['Comment'],
                $shift['freeloaded'],
                $shift['freeload_comment'],
                in_array('user_shifts_admin', $privileges)
            );
        } else {
            redirect(page_link_to('user_myshifts'));
        }
    } elseif (isset($_REQUEST['cancel']) && preg_match('/^\d*$/', $_REQUEST['cancel'])) {
        $user_id = $_REQUEST['cancel'];
        $shift = DB::select('
                SELECT *
                FROM `Shifts`
                INNER JOIN `ShiftEntry` USING (`SID`)
                WHERE `ShiftEntry`.`id`=? AND `UID`=?
            ',
            [
                $user_id,
                $shifts_user['UID'],
            ]
        );
        if (count($shift) > 0) {
            $shift = array_shift($shift);
            if (
                ($shift['start'] > time() + config('last_unsubscribe') * 3600)
                || in_array('user_shifts_admin', $privileges)
            ) {
                $result = ShiftEntry_delete($user_id);
                if ($result === false) {
                    engelsystem_error('Unable to delete shift entry.');
                }
                $room = Room($shift['RID']);
                $angeltype = AngelType($shift['TID']);
                $shifttype = ShiftType($shift['shifttype_id']);

                engelsystem_log(
                    'Deleted own shift: ' . $shifttype['name']
                    . ' at ' . $room['Name']
                    . ' from ' . date('Y-m-d H:i', $shift['start'])
                    . ' to ' . date('Y-m-d H:i', $shift['end'])
                    . ' as ' . $angeltype['name']
                );
                success(_('Shift canceled.'));
            } else {
                error(_('It\'s too late to sign yourself off the shift. If neccessary, ask the dispatcher to do so.'));
            }
        } else {
            redirect(user_link($shifts_user));
        }
    }

    redirect(page_link_to('users') . '&action=view&user_id=' . $shifts_user['UID']);
    return '';
}
