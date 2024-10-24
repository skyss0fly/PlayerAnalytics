<?php
namespace skyss0fly\PlayerAnalytics;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use use skyss0fly\PlayerAnalytics\form\{Form, SimpleForm};
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;

class Main extends PluginBase implements Listener {

    private $playerData = [];
    private $serverData = [
        'totalPlayers' => 0,
        'totalBlocksBroken' => 0,
        'totalItemsConsumed' => 0,
    ];

    public function onEnable(): void {
        // Load the configuration
        $this->saveDefaultConfig();
        $this->getLogger()->info(TF::GREEN . "PlayerAnalytics has been enabled.");
        
        // Load player data
        $this->loadPlayerData();

        // Register event listeners
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $this->initializePlayerData($player);
        $this->playerData[$player->getName()]['loginCount']++;
        $this->playerData[$player->getName()]['lastLogin'] = time();
        $this->playerData[$player->getName()]['sessionPlayTime'] = 0; // Initialize session playtime
        $this->playerData[$player->getName()]['recentActivities'] = []; // Initialize recent activities
        $this->serverData['totalPlayers']++;
        $this->savePlayerData();
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $playTime = time() - $this->playerData[$player->getName()]['lastLogin'];
        $this->playerData[$player->getName()]['totalPlayTime'] += $playTime;
        $this->playerData[$player->getName()]['sessionPlayTime'] += $playTime; // Update session playtime
        $this->savePlayerData();
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $this->playerData[$player->getName()]['messagesSent']++;
        $this->recordRecentActivity($player->getName(), "Sent a message");
        $this->savePlayerData();
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $this->playerData[$player->getName()]['blocksBroken']++;
        $this->serverData['totalBlocksBroken']++;
        $this->recordRecentActivity($player->getName(), "Broke a block");
        $this->savePlayerData();
    }

    public function onPlayerItemConsume(PlayerItemConsumeEvent $event): void {
        $player = $event->getPlayer();
        $this->playerData[$player->getName()]['itemsConsumed']++;
        $this->serverData['totalItemsConsumed']++;
        $this->recordRecentActivity($player->getName(), "Consumed an item");
        $this->savePlayerData();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "playerstats") {
            if (count($args) === 0) {
                $this->showPlayerStatsForm($sender, $sender->getName());
            } else {
                $this->showPlayerStatsForm($sender, $args[0]);
            }
            return true;
        } elseif ($command->getName() === "serveranalytics") {
            $this->showServerAnalyticsForm($sender);
            return true;
        }
        return false;
    }

    private function initializePlayerData($player): void {
        if (!isset($this->playerData[$player->getName()])) {
            $this->playerData[$player->getName()] = [
                'loginCount' => 0,
                'totalPlayTime' => 0,
                'sessionPlayTime' => 0, // Track session playtime
                'messagesSent' => 0,
                'blocksBroken' => 0,
                'itemsConsumed' => 0,
                'lastLogin' => time(),
                'dailyPlaytime' => [], // Track daily playtime
                'weeklyPlaytime' => [], // Track weekly playtime
                'monthlyPlaytime' => [], // Track monthly playtime
                'recentActivities' => [], // Initialize recent activities
            ];
        }
    }

    private function recordRecentActivity(string $playerName, string $activity): void {
        $timestamp = date("Y-m-d H:i:s");
        $this->playerData[$playerName]['recentActivities'][] = "$timestamp: $activity";
        if (count($this->playerData[$playerName]['recentActivities']) > 5) {
            array_shift($this->playerData[$playerName]['recentActivities']); // Keep last 5 activities
        }
    }

    private function showPlayerStatsForm(CommandSender $sender, string $playerName): void {
        if (!isset($this->playerData[$playerName])) {
            $sender->sendMessage(TF::RED . "No data found for player $playerName.");
            return;
        }

        $stats = $this->playerData[$playerName];
        $form = new SimpleForm(function (CommandSender $sender, $data) {
            // Form response handling can be implemented here
        });

        $form->setTitle("Player Statistics for $playerName");
        $form->addLabel("Login Count: " . $stats['loginCount']);
        $form->addLabel("Total Playtime: " . gmdate("H:i:s", $stats['totalPlayTime']));
        $form->addLabel("Session Playtime: " . gmdate("H:i:s", $stats['sessionPlayTime']));
        $form->addLabel("Messages Sent: " . $stats['messagesSent']);
        $form->addLabel("Blocks Broken: " . $stats['blocksBroken']);
        $form->addLabel("Items Consumed: " . $stats['itemsConsumed']);

        // Add playtime breakdown
        $dailyPlaytime = array_sum($stats['dailyPlaytime'] ?? []);
        $weeklyPlaytime = array_sum($stats['weeklyPlaytime'] ?? []);
        $monthlyPlaytime = array_sum($stats['monthlyPlaytime'] ?? []);
        $form->addLabel("Daily Playtime: " . gmdate("H:i:s", $dailyPlaytime));
        $form->addLabel("Weekly Playtime: " . gmdate("H:i:s", $weeklyPlaytime));
        $form->addLabel("Monthly Playtime: " . gmdate("H:i:s", $monthlyPlaytime));

        // Add recent activities
        $form->addLabel("Recent Activities:");
        foreach ($stats['recentActivities'] as $activity) {
            $form->addLabel("- $activity");
        }

        $sender->sendForm($form);
    }

    private function showServerAnalyticsForm(CommandSender $sender): void {
        $form = new SimpleForm(function (CommandSender $sender, $data) {
            // Form response handling can be implemented here
        });

        $form->setTitle("Server Analytics");
        $form->addLabel("Total Players Online: " . $this->serverData['totalPlayers']);
        $form->addLabel("Total Blocks Broken: " . $this->serverData['totalBlocksBroken']);
        $form->addLabel("Total Items Consumed: " . $this->serverData['totalItemsConsumed']);

        $sender->sendForm($form);
    }

    private function loadPlayerData(): void {
        $this->playerData = [];
        $dataFile = $this->getDataFolder() . "playerdata.yml";

        if (file_exists($dataFile)) {
            $data = yaml_parse_file($dataFile);
            if (is_array($data)) {
                $this->playerData = $data;
            }
        }
    }

    private function savePlayerData(): void {
        $dataFile = $this->getDataFolder() . "playerdata.yml";
        yaml_emit_file($dataFile, $this->playerData);
    }
}
