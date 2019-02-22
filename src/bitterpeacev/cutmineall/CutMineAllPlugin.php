<?php

namespace bitterpeacev\cutmineall;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;

class CutMineAllPlugin extends PluginBase implements Listener
{
    private $targets = [14, 15, 16, 17, 21, 56, 73, 74, 129, 153, 162];
    private $searched = [];
    private $switch = [];
    
    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($label === "cm-all") {
            if (!isset($args[0])) return false;

            if ($sender instanceof Player) {
                $switch = $args[0] === "on" ? true : false;
                $this->switch[$sender->getName()] = $switch;
                $sender->sendMessage("一括破壊機能を{$args[0]}にしました");
                return true;
            }
        }
        return false;
    }

    /**
     * @priority MONITOR
     * @ignoreCancelled
     */
    function onBlockBreak(BlockBreakEvent $event)
    {
        $block = $event->getBlock();
        $player = $event->getPlayer();
        $name = $player->getName();
        $vector = $block->asVector3();
        $item = $player->getInventory()->getItemInHand();

        // プレイヤーが木こり機能をオフにしていればここで処理を終える
        if (!isset($this->switch[$name]) || !$this->switch[$name]) {
            return;
        }

        // 木や鉱石でなければここで処理を終える
        if (!in_array($block->getId(), $this->targets, true)) {
            return;
        }

        // プレイヤーの探索済み座標リストが無ければ作る
        if (!isset($this->searched[$name])) {
            $this->searched[$name] = [];
        }
        
        // リストにブロックの座標があればここで処理を終える
        if (in_array($vector, $this->searched[$name], true)) {
            return;
        }
        
        // リストにブロックの座標を加える
        $this->searched[$name][] = $vector;
        
        // 隣接している6ブロックを探索する
        $i = 0;
        $nVector;
        foreach ($block->getAllSides() as $neighbor) {
            $nVector = $neighbor->asVector3();
            
            // リストに隣接するブロックの座標がある or 掘ったブロックと隣接するブロックのIDが違う場合、スキップして次のブロックへ
            if (in_array($nVector, $this->searched[$name], true) || $block->getId() !== $neighbor->getId()) {
                continue;
            }

            $i++;
            
            // 数tick遅らせて掘る
            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(
                function (int $currentTick) use ($nVector, $item, $player): void
                {
                    // 遅延が生じるので既に掘られている可能性がある
                    // その場合は掘らずに処理をここで終える
                    if ($player->level->getBlock($nVector)->getId() === 0) {
                        return;
                    }
                    
                    // 掘る。その際にBlockBreakEventが発生する（再帰処理）
                    $player->level->useBreakOn($nVector, $item, $player, true);
                }
            ), $i);
        }

        // 掘ったのでリストから削除する
        $this->searched[$name] = array_values(array_diff($this->searched[$name], [$vector]));
    }
}
