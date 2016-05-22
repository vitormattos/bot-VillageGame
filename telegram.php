<?php
use PhpTelegram\Client;
require('vendor/autoload.php');
require 'config.php';
try {
    if($config['telegram.cli']['tcp-port']) {
        $remoteSocket = 'tcp://localhost:'.$config['telegram.cli']['tcp-port'];
    } elseif($config['telegram.cli']['udp-socket']) {
        $remoteSocket = 'unix://'.$config['telegram.cli']['udp-socket'];
    } else {
        throw new Exception('Inform the type of connection (tcp || socket)');
    }
    $telegram = new Client($remoteSocket);
} catch(Exception $e) {
    echo $e->getMessage();
    die();
}

if(!isset($argv[1])) {
    die("Informe um usuário\n");
}
$user = $telegram->exec('resolve_username '.$telegram->escapePeer($argv[1]));
if(!$user) {
    die("Usuário inválido\n");
}
$i = 0;
$next = 'salvar';
$command = null;
$text = null;
$send_statistic = false;
$estatistica = [
    'start' => new DateTime(),
    'salvar' => 0,
    'proteger' => 0,
    'reforcos' => 0,
    'ataque' => 0
];
$config = [
    '🚀Missões' => [
        'text' => 'return @$history[$position]->text;',
        'position' => [1],
        'command' => function(&$command, $text, &$next) {
            $command = $next == 'proteger'
                ? '⭐️⭐️Proteger a caravana'
                : '⭐️⭐️⭐️Salvar a vila';
        }
    ],
    'start' => [
        'text' => 'return @$history[$position]->text;',
        'position' => [1],
        'command' => function(&$command, $text, &$next) {
            global $estatistica;
            $estatistica[$next]++;
            $command = $next == 'proteger'
                ? '⭐️⭐️Proteger a caravana'
                : '⭐️⭐️⭐️Salvar a vila';
        }
    ],
    'stop' => [
        'text' => 'return @$history[$position]->text;',
        'position' => [0,1,2],
        'command' => function(&$command, $text, &$next) {
            $command = in_array(strtolower($text), array('stop', 'pause'))
                ? 'stop'
                : $command;
        }
    ],
    'revenge' => [
        'to_search' => ['Você foi atacado'],
        'text' => 'return @$history[$position]->text;',
        'position' => [2],
        'command' => function(&$command, $text, &$next) {
            global $estatistica;
            $estatistica['ataque']++;
            preg_match('/(?<command>\/revenge_\d+)/', $text, $matches);
            $command = $matches['command'];
        }
    ],
    'atacar' => [
        'to_search' => ['Seu inimigo é'],
        'text' => 'return @$history[$position]->text;',
        'position' => [2],
        'command' => 'Atacar!⚔'
    ],
    'start_2' => [
        'to_search' =>[
            'Sua tropa foi destruída...',
            'Sua tropa protegeu a caravana',
            'Sua tropa veio para o resgate',
            'Esses bandidos eram covardes',
            'Excelente!',
            'Você começou a trabalhar',
            'Você pode lutar contra outros jogadores',
            'Você pode escolher uma missão',
            'Você já está trabalhando'
        ],
        'text' => 'return @$history[$position]->text;',
        'position' => [2],
        'command' => function(&$command, $text, &$next) {
            global $estatistica;
            $estatistica[$next]++;
            $command = $next == 'proteger'
                ? '⭐️⭐️Proteger a caravana'
                : '⭐️⭐️⭐️Salvar a vila';
        }
    ],
    'fazer missão' => [
        'to_search' => ['Bandidos atacaram a vila.', 'Uma caravana passa perto de sua vila.'],
        'text' => 'return @$history[$position]->media->caption;',
        'position' => [2],
        'command' => 'Fazer missão🗡'
    ],
    'mandar reforços' => [
        'to_search' => [
            'A caravana foi atacada e seus guardas mal conseguem',
            'Durante a batalha, o inimigo levantou uma milícia',
            'Seu time não foi suficiente',
            'seus guardas mal conseguem',
            'Os bandidos eram uns caras fortes'
        ],
        'text' => 'return @$history[$position]->text;',
        'position' => [2],
        'command' => function(&$command, $text, &$next) {
            global $estatistica;
            $estatistica['reforcos']++;
            $next = $next == 'salvar' ? 'proteger' : 'salvar';
            $command = 'Mandar reforços! 🗡';
        }
    ],
    [
        'to_search' => ['Trabalho terminado, meu senhor!'],
        'text' => 'return @$history[$position]->text;',
        'position' => [0,1],
        'command' => '/work'
    ],
    [
        'to_search' => ['Trabalho terminado, meu senhor!', 'Você vendeu'],
        'text' => 'return @$history[$position]->text;',
        'position' => [2,2],
        'command' => '/work'
    ],
    [
        'to_search' => ['Seu campo está cheio'],
        'text' => 'return @$history[$position]->text;',
        'position' => [1,2],
        'command' => '/harvest'
    ],
    'estatistica' => [
        'to_search' => ['Trabalhadores: '],
        'text' => 'return @$history[$position]->text;',
        'position' => [0,1,2],
        'command' => function(&$command, $text, &$next) use($send_statistic) {
            global $telegram, $history, $position, $user, $estatistica, $matches;
            preg_match(
                '/Nível (?<nivel>\d+)🏘, (?<percent>\d+(\.\d+)?%)[\s\S]*Ouro: (?<ouro>\d+)[\s\S]*Medalhas: (?<medalhas>\d+)/',
                $text,
                $matches
            );
            //$telegram->deleteMsg($history[$position]->id);
            if($send_statistic && isset($matches['nivel'])) {
                $estatistica[$next]++;
                $telegram->msg($user->id, "Nível:{$matches['nivel']} {$matches['percent']} Ouro: {$matches['ouro']} Medalhas: {$matches['medalhas']}");
            }
            $command = $next == 'proteger'
                ? '⭐️⭐️Proteger a caravana'
                    : '⭐️⭐️⭐️Salvar a vila';
        },
        'break' => false
    ]
];
$telegram->msg('$010000009348a00bc2714ae0add24a6c', '/refresh_data');
while(true) {
    $history = $telegram->getHistory('$010000009348a00bc2714ae0add24a6c', 1);
    if(isset($history[0]->text)) {
        $config['estatistica']['command']($command, $history[0]->text, $next);
        if(isset($matches['nivel'])) {
            $estatistica['about_my_village'] = $matches;
            break;
        }
    }
}
$send_statistic = true;
while(true) {
    $history = $telegram->getHistory('$010000009348a00bc2714ae0add24a6c', 3);
    if($command == 'stop') {
        $text = eval($config['start']['text']);
        $command = $config['start']['command']($command, $text, $next);
    }
    if($command != 'stop') {
        foreach($config as $to_search => $params) {
            $to_search = isset($params['to_search'])
                ? $params['to_search']
                : [$to_search];
            foreach($params['position'] as $position) {
                $text = eval($params['text']);
                foreach($to_search as $key) {
                    if(strpos($text, $key) !== false) {
                        if(is_callable($params['command'])) {
                            $params['command']($command, $text, $next);
                        } else {
                            $command = $params['command'];
                        }
                        if(!isset($params['break']) || $params['break']) {
                            break 3;
                        }
                    }
                }
            }
        }
    }
    foreach(array('salvar', 'proteger') as $item) {
        if($estatistica[$item] >= 100) {
            $telegram->msg('$010000009348a00bc2714ae0add24a6c', '/refresh_data');
            $send_statistic = false;
            while(true) {
                $history = $telegram->getHistory('$010000009348a00bc2714ae0add24a6c', 1);
                $config['estatistica']['command']($command, $history[0]->text, $next);
                if(isset($matches['nivel'])) {
                    break;
                }
            }
            $send_statistic = true;
            $telegram->msg($user->id,
                "Nível:{$matches['nivel']} {$matches['percent']}\n".
                "Ouro: ".($matches['ouro'] - $estatistica['about_my_village']['ouro'])." Total: {$matches['ouro']}\n".
                "Medalhas: {$estatistica['about_my_village']['medalhas']} +".($matches['medalhas'] - $estatistica['about_my_village']['medalhas'])."\n".
                "Salvar: {$estatistica['salvar']}\n".
                "Proteger: {$estatistica['proteger']}\n".
                "Reforços: {$estatistica['reforcos']}\n".
                "Ataques: {$estatistica['ataque']}\n".
                "Intervalo: ".$estatistica['start']->diff(new DateTime())->format('%H:%i:%s')
            );
            $estatistica = [
                'about_my_village' => $matches,
                'start' => new DateTime(),
                'salvar' => 0,
                'proteger' => 0,
                'reforcos' => 0,
                'ataque' => 0
            ];
            break;
        }
    }
    if($command && $command !== 'stop') {
        $telegram->msg('$010000009348a00bc2714ae0add24a6c', $command);
        echo "$i -> {$command} -> $next\n";
        $command = null;
    } else {
        if($command == 'stop') {
            echo "$i -> pause -> $next\n";
        } else {
            echo "$i -> no command -> $next\n";
        }
    }
    $i++;
    sleep(rand(3, 5));
}
