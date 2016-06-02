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

class AutoplayVillage
{
    public $config;
    public $command;
    private $next = 'salvar';
    private $msg;
    private $user;
    private $estatistica;
    private $send_statistic = false;
    private $iteration = 0;
    private $limit = 10;
    /**
     * @var Client
     */
    private $telegram;
    public function __construct($telegram, $argv) {
        $this->telegram = $telegram;
        if(!isset($argv[1])) {
            die("Informe um usuário\n");
        }
        $this->user = $this->telegram->exec('resolve_username '.$this->telegram->escapePeer($argv[1]));
        if(!$this->user) {
            die("Usuário inválido\n");
        }
        $this->estatistica = [
            'start' => new DateTime(),
            'salvar' => 0,
            'proteger' => 0,
            'reforcos' => 0,
            'ataque' => 0
        ];
        $this->config = [
            '🚀Missões' => [
                'text' => 'return @$msg->text;',
                'position' => [1],
                'command' => function($text) {
                    $this->command = $this->next == 'proteger'
                        ? 'Proteger a caravana⭐️⭐️'
                        : 'Salvar a vila⭐️⭐️⭐️';
                }
            ],
            'start' => [
                'text' => 'return @$msg->text;',
                'position' => [1],
                'command' => function($text) {
                    $this->estatistica[$this->next]++;
                    $this->command = $this->next == 'proteger'
                        ? 'Proteger a caravana⭐️⭐️'
                        : 'Salvar a vila⭐️⭐️⭐️';
                }
            ],
            'stop' => [
                'text' => 'return @$msg->text;',
                'position' => [0,1,2],
                'command' => function($text) {
                    $this->command = in_array(strtolower($text), array('stop', 'pause'))
                        ? 'stop'
                        : $this->command;
                }
            ],
            'mandar reforços' => [
                'to_search' => [
                    'A caravana foi atacada e seus guardas mal conseguem',
                    'Durante a batalha, o inimigo levantou uma milícia',
                    'Seu time não foi suficiente',
                    'seus guardas mal conseguem',
                    'Os bandidos eram uns caras fortes'
                ],
                'text' => 'return @$msg->text;',
                'position' => [1,2],
                'command' => function($text) {
                    $this->estatistica['reforcos']++;
                    $this->next = $this->next == 'salvar' ? 'proteger' : 'salvar';
                    $this->command = 'Mandar reforços! 🗡';
                }
            ],
            'revenge' => [
                'to_search' => ['Você foi atacado'],
                'text' => 'return @$msg->text;',
                'position' => [2],
                'command' => function($text) {
                    $this->estatistica['ataque']++;
                    //preg_match('/(?<command>\/revenge_\d+)/', $text, $matches);
                    //$this->command = $matches['command'];
                    $this->estatistica[$this->next]++;
                    $this->command = $this->next == 'proteger'
                        ? 'Proteger a caravana⭐️⭐️'
                        : 'Salvar a vila⭐️⭐️⭐️';
                }
            ],
            'atacar' => [
                'to_search' => ['Seu inimigo é'],
                'text' => 'return @$msg->text;',
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
                'text' => 'return @$msg->text;',
                'position' => [2],
                'command' => function($text) {
                    $this->estatistica[$this->next]++;
                    $this->command = $this->next == 'proteger'
                        ? 'Proteger a caravana⭐️⭐️'
                        : 'Salvar a vila⭐️⭐️⭐️';
                }
            ],
            'fazer missão' => [
                'to_search' => [
                    'Bandidos atacaram a vila.',
                    'Uma caravana passa perto de sua vila.'
                ],
                'text' => 'return @$msg->media->caption;',
                'position' => [2],
                'command' => 'Fazer missão🗡'
            ],
            [
                'to_search' => ['Trabalho terminado, meu senhor!'],
                'text' => 'return @$msg->text;',
                'position' => [0,1],
                'command' => '/work'
            ],
            [
                'to_search' => ['Trabalho terminado, meu senhor!', 'Você vendeu'],
                'text' => 'return @$msg->text;',
                'position' => [2,2],
                'command' => '/work'
            ],
            [
                'to_search' => ['Seu campo está cheio'],
                'text' => 'return @$msg->text;',
                'position' => [1,2],
                'command' => '/harvest'
            ],
            'estatistica' => [
                'to_search' => ['Trabalhadores: '],
                'text' => 'return @$msg->text;',
                'position' => [0,1,2],
                'command' => function($text) {
                    preg_match(
                        '/Nível (?<nivel>\d+)🏘, (?<percent>\d+(\.\d+)?%)[\s\S]*Ouro: (?<ouro>\d+)[\s\S]*Medalhas: (?<medalhas>\d+)/',
                        $text,
                        $matches
                    );
                    $this->command = $this->next == 'proteger'
                        ? 'Proteger a caravana⭐️⭐️'
                            : 'Salvar a vila⭐️⭐️⭐️';
                    if(isset($matches['nivel'])) {
                        if($this->send_statistic) {
                            $this->estatistica[$this->next]++;
                            $this->telegram->msg($this->user->id, "Nível:{$matches['nivel']} {$matches['percent']} Ouro: {$matches['ouro']} Medalhas: {$matches['medalhas']}");
                        }
                        $this->estatistica['about_my_village'] = $matches;
                        return true;
                    }
                    return false;
                },
                'break' => false
            ]
        ];
    }
    
    public function startStatistics() {
        $this->last['date'] = time();
        $this->last['id'] = null;
        $this->telegram->msg('$010000009348a00bc2714ae0add24a6c', '/refresh_data');
        $i = 0;
        while(true) {
            if($history = $this->telegram->getHistory('$010000009348a00bc2714ae0add24a6c', 1)) {
                if($history[0]->date > $this->last['date']) {
                    $this->config['estatistica']['command']($history[0]->text);
                    if($this->command) {
                        $this->last['date'] = $history[0]->date-1;
                        $this->last['id'] = $history[0]->id;
                        break;
                    }
                }
            }
            echo "\rEstatística inicial: $i";
            usleep(500000);
            $i++;
        }
        echo "\n";
    }
    
    public function getMyLastCommand() {
        if($history = $this->telegram->getHistory('$010000009348a00bc2714ae0add24a6c', $this->limit)){
            $index = count($history);
            while($index >= 0) {
                $msg = $history[--$index];
                if($msg->from->id == $this->user->id) {
                    $this->last['date'] = $msg->date;
                    $this->last['id'] = $msg->id;
                    break;
                }
            }
        }
    }
    
    public function getNextCommand() {
        if($this->command == 'stop') {
            $text = eval($this->config['start']['text']);
            $this->command = $this->config['start']['command']($text);
        }
        if($this->command != 'stop') {
            $this->command = null;
            $i = 0;
            while(!$this->command) {
                echo "\rEstatística getNextCommand $i";
                if($history = $this->telegram->getHistory('$010000009348a00bc2714ae0add24a6c', $this->limit)){
                    foreach($history as $key => $msg) {
                        if($this->last['id'] == $msg->id && isset($history[$key+1])) {
                            $msg = $history[$key+1];
                            $this->last['date'] = $msg->date;
                            $this->last['id'] = $msg->id;
                            $this->limit = count($history) - $this->limit > 0
                                ? count($history) - $this->limit
                                : $this->limit;
                            if($this->matchCommand($msg)) {
                                break 2;
                            }
                        }
                    }
                }
                $this->limit++;
                $i++;
                usleep(500000);
            }
            echo "\n";
            return $i;
        }
    }
    
    public function matchCommand($msg) {
        foreach($this->config as $to_search => $params) {
            if($text = eval($params['text'])) {
                $to_search = isset($params['to_search'])
                    ? $params['to_search']
                    : [$to_search];
                foreach($to_search as $key) {
                    if(strpos($text, $key) !== false) {
                        if(is_callable($params['command'])) {
                            $params['command']($text);
                        } else {
                            $this->command = $params['command'];
                        }
                        if(!isset($params['break']) || $params['break']) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
    
    public function processStatistics() {

        foreach(array('salvar', 'proteger') as $item) {
            if($this->estatistica[$item] >= 100) {
                $this->telegram->msg('$010000009348a00bc2714ae0add24a6c', '/refresh_data');
                $this->send_statistic = false;
                while(true) {
                    if($history = $this->telegram->getHistory('$010000009348a00bc2714ae0add24a6c', 1)) {
                        if(isset($history[0]->text)) {
                            if($this->config['estatistica']['command']($this->command, $history[0]->text)) {
                                break;
                            }
                        }
                    }
                    usleep(500000);
                }
                $this->send_statistic = true;
                $this->telegram->msg($this->user->id,
                    "Nível:{$matches['nivel']} {$matches['percent']}\n".
                    "Ouro: ".($matches['ouro'] - $this->estatistica['about_my_village']['ouro'])." Total: {$matches['ouro']}\n".
                    "Medalhas: {$this->estatistica['about_my_village']['medalhas']} +".($matches['medalhas'] - $this->estatistica['about_my_village']['medalhas'])."\n".
                    "Salvar: {$this->estatistica['salvar']}\n".
                    "Proteger: {$this->estatistica['proteger']}\n".
                    "Reforços: {$this->estatistica['reforcos']}\n".
                    "Ataques: {$this->estatistica['ataque']}\n".
                    "Intervalo: ".$this->estatistica['start']->diff(new DateTime())->format('%H:%i:%s')
                );
                $this->estatistica = [
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
    }
    
    public function sendCommand() {
        $this->iteration++;
        if($this->command && $this->command !== 'stop') {
            $this->telegram->msg('$010000009348a00bc2714ae0add24a6c', $this->command);
            echo "{$this->iteration} -> {$this->command} -> {$this->next}\n";
            $this->command = null;
            return true;
        } else {
            if($this->command == 'stop') {
                echo "{$this->iteration} -> pause -> {$this->next}\n";
            } else {
                echo "{$this->iteration} -> no command -> {$this->next}\n";
            }
        }
    }
}

$Village = new AutoplayVillage($telegram, $argv);
$Village->startStatistics();
$Village->getMyLastCommand();
while(true) {
    $total = $Village->getNextcommand();
    $Village->processStatistics();
    $Village->sendCommand();
    sleep(rand($total, $total+2));
}
