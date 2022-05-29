<?php
declare(strict_types=1);

namespace Texter\form;

use pocketmine\form\Form;
use pocketmine\player\Player;
use Texter\Texter;
use function trim;

class CreateForm implements Form{

	public function jsonSerialize() : array{
		return [
			"type" => "custom_form",
			"title" => "텍스트 생성하기",
			"content" => [
				[
					"type" => "input",
					"text" => "텍스트의 내용\n줄바꿈은 (줄바꿈) 으로 가능."
				]
			]
		];
	}

	public function handleResponse(Player $player, $data) : void{
		if(trim($data[0] ?? "") === ""){
			$player->sendMessage(Texter::$prefix . "텍스트를 입력해주세요.");
			return;
		}

		Texter::getInstance()->addText($player->getPosition(), (string) $data[0]);
		$player->sendMessage(Texter::$prefix . "생성되었습니다.");
	}
}