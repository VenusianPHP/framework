<?php

use Voyager\Database\Console\Migrations\TableGuesser;

test('migration is properly parsed', function () {
    [$table, $create] = TableGuesser::guess('create_users_table');
    expect($table)->toBe('users');
    expect($create)->toBeTrue();

    [$table, $create] = TableGuesser::guess('add_status_column_to_users_table');
    expect($table)->toBe('users');
    expect($create)->toBeFalse();

    [$table, $create] = TableGuesser::guess('add_is_sent_to_crm_column_to_users_table');
    expect($table)->toBe('users');
    expect($create)->toBeFalse();

    [$table, $create] = TableGuesser::guess('change_status_column_in_users_table');
    expect($table)->toBe('users');
    expect($create)->toBeFalse();

    [$table, $create] = TableGuesser::guess('drop_status_column_from_users_table');
    expect($table)->toBe('users');
    expect($create)->toBeFalse();
});

test('migration is properly parsed without table suffix', function () {
    [$table, $create] = TableGuesser::guess('create_users');
    expect($table)->toBe('users');
    expect($create)->toBeTrue();

    [$table, $create] = TableGuesser::guess('add_status_column_to_users');
    expect($table)->toBe('users');
    expect($create)->toBeFalse();

    [$table, $create] = TableGuesser::guess('add_is_sent_to_crm_column_column_to_users');
    expect($table)->toBe('users');
    expect($create)->toBeFalse();

    [$table, $create] = TableGuesser::guess('change_status_column_in_users');
    expect($table)->toBe('users');
    expect($create)->toBeFalse();

    [$table, $create] = TableGuesser::guess('drop_status_column_from_users');
    expect($table)->toBe('users');
    expect($create)->toBeFalse();
});
