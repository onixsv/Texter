<?php
declare(strict_types=1);

namespace Texter\task;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use Texter\Texter;

class ShowTextTask extends Task{

	public function onRun() : void{
		foreach(Texter::getInstance()->getTexts() as $text){
			if($text->hasValidPos()){
				foreach(Server::getInstance()->getOnlinePlayers() as $player){
					if($player->isConnected() and $player->isAlive()){
						if(($text->distance($player->getPosition()) <= Texter::getInstance()->getConfig()->getNested("distance", 8)) && $player->getWorld()->getFolderName() === $text->getPosition()->getWorld()->getFolderName()){
							$text->spawnTo($player);
						}else{
							$text->despawnTo($player);
						}
					}
				}
			}
		}
	}
}