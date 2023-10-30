<?php

include ("../../../inc/includes.php");

global $DB;

$query = <<<SQL
    SELECT glpi_tickets_users.tickets_id as tid,
           glpi_useremails.email,
           glpi_tickets_users.users_id,
           glpi_tickets_users.type,
           glpi_tickets_users.alternative_email,
           glpi_tickets.name,
           glpi_tickets.is_deleted
    FROM glpi_tickets_users
    LEFT JOIN glpi_useremails
    ON (glpi_tickets_users.users_id = glpi_useremails.users_id)
    LEFT JOIN glpi_tickets
    ON (glpi_tickets.id = glpi_tickets_users.tickets_id)
    WHERE 1=1
    AND (glpi_useremails.email = 'test@google.com' OR glpi_tickets_users.alternative_email = 'test@google.com')
    AND glpi_tickets_users.type = '1'
    AND glpi_tickets.is_deleted = '0'
    AND glpi_tickets.name like '%(JIRA-1234)%'
    SQL;

echo "<pre>";

$result = $DB->query($query) or die($DB->error());
while($row = $result->fetch_assoc()){
    print_r($row['tid'].':'.$row['email'].':'.$row['alternative_email'].':'.$row['name'].'<br>');
}

var_dump($DB->request(
    [
        'FIELDS' => ['glpi_tickets_users' => ['tickets_id', 'users_id', 'type', 'alternative_email'], 
                     'glpi_tickets'       => ['name'],
                     'glpi_useremails'    => ['email']
                    ],
        'FROM' => 'glpi_tickets_users', 'glpi_useremails', 'glpi_tickets',
        'FKEY' => [
                    ['glpi_tickets_users' => 'users_id',
                    'glpi_useremails' => 'users_id'], 
                    ['glpi_tickets_users' => 'tickets_id', 
                    'glpi_tickets' => 'id']
                  ] 
    ]
));

