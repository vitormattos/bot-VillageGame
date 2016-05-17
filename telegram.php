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
$pause = false;
while(true) {
    $history = $telegram->getHistory('$010000009348a00bc2714ae0add24a6c', 3);
    if($history[1]->from->id == $user->id || $history[2]->from->id == $user->id) {
        if($history[1]->text == "pause" || $history[1]->text == "stop") {
            $command = null;
            $pause = true;
        }
        if($history[1]->text == "start") {
            $pause = false;
        }
        if(!$pause)
        if(
            strpos($history[1]->text, "🚀Missões") !== false ||
            (
                isset($history[2]->text) && 
                strpos($history[2]->text, "Excelente!") !== false
            )
            ) {
            if($next == 'proteger') {
                $command = "⭐️⭐️Proteger a caravana";
            } else {
                $command = "⭐️⭐️⭐️Salvar a vila";
            }
        } else {
            if(isset($history[2]->media, $history[2]->media->caption)) {
                if(strpos($history[2]->media->caption, "Bandidos atacaram a vila.") !== false) {
                    $command = "Fazer missão🗡";
                } elseif(strpos($history[2]->media->caption, "Uma caravana passa perto de sua vila.") !== false) {
                    $command = "Fazer missão🗡";
                } else {
                    $command = null;
                }
            } elseif(isset($history[2]->text)) {
                if(
                    strpos($history[2]->text, "seus guardas mal conseguem") !== false ||
                    strpos($history[2]->text, "Os bandidos eram uns caras fortes") !== false
                    ) {
                    $next = $next == 'salvar' ? 'proteger' : 'salvar';
                    $command = "Mandar reforços! 🗡";
                } elseif(strpos($history[2]->text, "A caravana foi atacada e seus guardas mal conseguem") !== false) {
                    $command = 'Mandar reforços! 🗡';
                } elseif(strpos($history[2]->text, "Seu time não foi suficiente") !== false) {
                    $command = 'Mandar reforços! 🗡';
                } elseif(
                    strpos($history[2]->text, "Sua tropa foi destruída....") !== false ||
                    strpos($history[2]->text, "Sua tropa protegeu a caravana") !== false ||
                    strpos($history[2]->text, "Sua tropa veio para o resgate") !== false ||
                    strpos($history[2]->text, "Esses bandidos eram covardes") !== false
                    ) {
                    if($next == 'proteger') {
                        $command = "⭐️⭐️Proteger a caravana";
                    } else {
                        $command = "⭐️⭐️⭐️Salvar a vila";
                    }
                } elseif(strpos($history[1]->text, "Seu campo está cheio") !== false) {
                    $command = "/harvest";
                } elseif(strpos($history[2]->text, "Seu campo está cheio") !== false) {
                    $command = "/harvest";
                } elseif(strpos($history[2]->text, "Você foi atacado por") !== false) {
                    preg_match('/(?<command>\/revenge_\d+)/', $history[2]->text, $matches);
                    $command = $matches['command'];
                } elseif(strpos($history[2]->text, "Seu inimigo é") !== false) {
                    $command = 'Atacar! ⚔';
                } elseif(strpos($history[2]->text, 'Durante a batalha, o inimigo levantou uma milícia') !== false) {
                    $command = 'Mandar reforços! 🗡';
                } elseif(
                    strpos($history[2]->text, 'Você pode lutar contra outros jogadores') !== false ||
                    strpos($history[2]->text, 'Você pode escolher uma missão') !== false
                    ) {
                    if($next == 'proteger') {
                        $command = "⭐️⭐️Proteger a caravana";
                    } else {
                        $command = "⭐️⭐️⭐️Salvar a vila";
                    }
                } elseif(strpos($history[2]->text, 'Você vendeu') !== false) {
                    $command = "🍞Trabalhar!";
                } elseif(strpos($history[2]->text, 'Trabalho terminado, meu senhor!') !== false) {
                    $command = '/work';
                } elseif(strpos($history[2]->text, 'Você começou a trabalhar') !== false) {
                    if($next == 'proteger') {
                        $command = "⭐️⭐️Proteger a caravana";
                    } else {
                        $command = "⭐️⭐️⭐️Salvar a vila";
                    }
                } elseif(strpos($history[2]->text, 'Trabalho terminado, meu senhor!') !== false) {
                    $command = "🍞Trabalhar!";
                } elseif(isset($history[1]->text) && strpos($history[1]->text, 'Trabalho terminado, meu senhor!') !== false) {
                    $command = "🍞Trabalhar!";
                } elseif(isset($history[0]->text) && strpos($history[0]->text, 'Trabalho terminado, meu senhor!') !== false) {
                    $command = "🍞Trabalhar!";
                } elseif($history[1]->text == "start") {
                    if($next == 'proteger') {
                        $command = "⭐️⭐️Proteger a caravana";
                    } else {
                        $command = "⭐️⭐️⭐️Salvar a vila";
                    }
                } else {
                    $command = null;
                }
            }
        }
    }
    if($command) {
        $telegram->msg('$010000009348a00bc2714ae0add24a6c', $command);
        echo "$i -> $command -> $next\n";
    } else {
        if($pause) {
            echo "$i -> pause -> $next\n";
        } else {
            echo "$i -> no command -> $next\n";
        }
    }
    $i++;
    sleep(rand(3, 5));
}