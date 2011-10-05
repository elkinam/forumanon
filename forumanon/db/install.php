<?php
function xmldb_forumanon_install() {
    global $CFG, $DB;
    $anon_name = get_config('forumanon', 'anonuser');
    if (empty($anon_name)) {
        set_config('anonuser', 'forumanon_anonymous', 'forumanon');
        $anon_name = 'forumanon_anonymous';
    }
    $anon_pw = mt_rand();
    if ($DB->count_records('user', array('username'=>$anon_name)) == 0)
        create_user_record($anon_name, $anon_pw);
    else
        // If someone else had created this user, they're now locked out.
        update_internal_user_password($anon_name, $anon_pw);
    $anon_pw = ''; // Just to make that clear.
    $anon_user = $DB->get_record('user', array('username'=>$anon_name));
    set_config('anonid', $anon_user->id, 'forumanon');
    $anon_user->firstname = '';
    $anon_user->lastname = 'Anonymous'; // TODO: i18n?
    $anon_user->email = ''; // It might have been set.
    $DB->update_record('user', $anon_user);
}

function xmldb_forumanon_install_recovery() {
	xmldb_forumanon_install();
}
