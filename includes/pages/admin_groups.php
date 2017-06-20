<?php

use Engelsystem\Database\DB;

/**
 * @return string
 */
function admin_groups_title()
{
    return _('Grouprights');
}

/**
 * @return string
 */
function admin_groups()
{
    $html = '';
    $groups = DB::select('SELECT * FROM `Groups` ORDER BY `Name`');
    if (!isset($_REQUEST['action'])) {
        $groups_table = [];
        foreach ($groups as $group) {
            $privileges = DB::select('
                SELECT `name`
                FROM `GroupPrivileges`
                JOIN `Privileges` ON (`GroupPrivileges`.`privilege_id` = `Privileges`.`id`)
                WHERE `group_id`=?
            ', [$group['UID']]);
            $privileges_html = [];

            foreach ($privileges as $privilege) {
                $privileges_html[] = $privilege['name'];
            }

            $groups_table[] = [
                'name'       => $group['Name'],
                'privileges' => join(', ', $privileges_html),
                'actions'    => button(
                    page_link_to('admin_groups') . '&action=edit&id=' . $group['UID'],
                    _('edit'),
                    'btn-xs'
                )
            ];
        }

        return page_with_title(admin_groups_title(), [
            table([
                'name'       => _('Name'),
                'privileges' => _('Privileges'),
                'actions'    => ''
            ], $groups_table)
        ]);
    } else {
        switch ($_REQUEST['action']) {
            case 'edit':
                if (isset($_REQUEST['id']) && preg_match('/^-\d{1,11}$/', $_REQUEST['id'])) {
                    $group_id = $_REQUEST['id'];
                } else {
                    return error('Incomplete call, missing Groups ID.', true);
                }

                $group = DB::select('SELECT * FROM `Groups` WHERE `UID`=? LIMIT 1', [$group_id]);
                if (!empty($group)) {
                    $privileges = DB::select('
                        SELECT `Privileges`.*, `GroupPrivileges`.`group_id`
                        FROM `Privileges`
                        LEFT OUTER JOIN `GroupPrivileges`
                            ON (
                                `Privileges`.`id` = `GroupPrivileges`.`privilege_id`
                                AND `GroupPrivileges`.`group_id`=?
                            )
                        ORDER BY `Privileges`.`name`
                    ', [$group_id]);
                    $privileges_html = '';
                    $privileges_form = [];
                    foreach ($privileges as $privilege) {
                        $privileges_form[] = form_checkbox(
                            'privileges[]',
                            $privilege['desc'] . ' (' . $privilege['name'] . ')',
                            $privilege['group_id'] != '',
                            $privilege['id']
                        );
                        $privileges_html .= sprintf(
                            '<tr><td><input type="checkbox" name="privileges[]" value="%s" %s /></td> <td>%s</td> <td>%s</td></tr>',
                            $privilege['id'],
                            ($privilege['group_id'] != '' ? 'checked="checked"' : ''),
                            $privilege['name'],
                            $privilege['desc']
                        );
                    }

                    $privileges_form[] = form_submit('submit', _('Save'));
                    $html .= page_with_title(_('Edit group'), [
                        form($privileges_form, page_link_to('admin_groups') . '&action=save&id=' . $group_id)
                    ]);
                } else {
                    return error('No Group found.', true);
                }
                break;

            case 'save':
                if (isset($_REQUEST['id']) && preg_match('/^-\d{1,11}$/', $_REQUEST['id'])) {
                    $group_id = $_REQUEST['id'];
                } else {
                    return error('Incomplete call, missing Groups ID.', true);
                }

                $group = DB::select('SELECT * FROM `Groups` WHERE `UID`=? LIMIT 1', [$group_id]);
                if (!is_array($_REQUEST['privileges'])) {
                    $_REQUEST['privileges'] = [];
                }
                if (!empty($group)) {
                    $group = array_shift($group);
                    DB::delete('DELETE FROM `GroupPrivileges` WHERE `group_id`=?', [$group_id]);
                    $privilege_names = [];
                    foreach ($_REQUEST['privileges'] as $privilege) {
                        if (preg_match('/^\d{1,}$/', $privilege)) {
                            $group_privileges_source = DB::select(
                                'SELECT `name` FROM `Privileges` WHERE `id`=? LIMIT 1',
                                [$privilege]
                            );
                            if (!empty($group_privileges_source)) {
                                $group_privileges_source = array_shift($group_privileges_source);
                                DB::insert(
                                    'INSERT INTO `GroupPrivileges` (`group_id`, `privilege_id`) VALUES (?, ?)',
                                    [$group_id, $privilege]
                                );
                                $privilege_names[] = $group_privileges_source['name'];
                            }
                        }
                    }
                    engelsystem_log(
                        'Group privileges of group ' . $group['Name']
                        . ' edited: ' . join(', ', $privilege_names)
                    );
                    redirect(page_link_to('admin_groups'));
                } else {
                    return error('No Group found.', true);
                }
                break;
        }
    }
    return $html;
}
