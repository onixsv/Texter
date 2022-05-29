<?php
declare(strict_types=1);

namespace Texter;

use OnixUtils\OnixUtils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\Position;
use Texter\form\CreateForm;
use Texter\form\RemoveForm;
use Texter\task\ShowTextTask;
use Texter\text\Text;
use UnexpectedValueException;
use function array_diff;
use function explode;
use function file_exists;
use function file_put_contents;
use function is_file;
use function rmdir;
use function scandir;
use function substr;
use function unlink;
use function yaml_emit;

function recursiveRmdir(string $dir) : void{
	if(substr($dir, -1) !== "/"){
		$dir .= "/";
	}
	foreach(scandir($dir) as $file){
		if($file !== "." and $file !== ".."){
			$realPath = $dir . $file;

			if(file_exists($realPath)){
				if(is_file($realPath)){
					unlink($realPath);
				}elseif(is_dir($realPath)){
					$ssss = array_diff(scandir($dir . $file), [".", ".."]);

					if(empty($ssss)){
						recursiveRmdir($realPath);
					}else{
						rmdir($realPath);
					}
				}else{
					throw new UnexpectedValueException();
				}
			}
		}
	}

	$ssss = array_diff(scandir($dir), [".", ".."]);

	if(!empty($ssss)){
		recursiveRmdir($dir);
	}else{
		rmdir($dir);
	}
}

class Texter extends PluginBase implements Listener{
	use SingletonTrait;

	public static string $prefix = "§b§l[알림] §r§7";

	/** @var Text[] */
	protected array $text = [];

	protected function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		$this->saveResource("config.yml");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$data = file_exists($this->getDataFolder() . "text.yml") ? yaml_parse(file_get_contents($this->getDataFolder() . "text.yml")) : [];

		foreach($data as $xyz => $string){
			$text = Text::jsonDeserialize($string);
			$this->text[$xyz] = $text;
		}

		if(file_exists($file = $this->getServer()->getDataPath() . "plugin_data/Tag/TagData.json")){
			$data = json_decode(file_get_contents($file), true);

			foreach($data as $xyz => $str){
				[, , , $world] = explode(":", $xyz);
				$this->text[$xyz] = new Text($str, OnixUtils::strToPos($xyz), $world);
			}

			recursiveRmdir($this->getServer()->getDataPath() . "plugin_data/Tag/");
		}

		$this->getScheduler()->scheduleRepeatingTask(new ShowTextTask(), 20);
	}

	protected function onDisable() : void{
		$this->save();
	}

	public function save() : void{
		$arr = [];
		foreach($this->text as $xyz => $text){
			$arr[$xyz] = $text->jsonSerialize();
		}
		file_put_contents($this->getDataFolder() . "text.yml", yaml_emit($arr));
	}

	public function addText(Position $pos, string $text){
		$t = new Text($text, $pos, $pos->getWorld()->getFolderName());
		$this->text[self::toString($pos)] = $t;
	}

	public function removeText(string $pos){
		$text = $this->text[$pos] ?? null;
		if($text instanceof Text){
			foreach(array_keys($text->hasSpawned) as $viewer){
				if(($target = $this->getServer()->getPlayerExact($viewer)) instanceof Player){
					$text->despawnTo($target);
				}
			}
			unset($this->text[$pos]);
		}
	}

	public static function toString(Position $pos) : string{
		return implode(":", [$pos->x, $pos->y, $pos->z, $pos->getWorld()->getFolderName()]);
	}

	public static function toPosition(string $str) : Position{
		[$x, $y, $z, $world] = explode(":", $str);

		return new Position((float) $x, (float) $y, (float) $z, Server::getInstance()->getWorldManager()->getWorldByName($world));
	}

	/**
	 * @return Text[]
	 */
	public function getTexts() : array{
		return array_values($this->text);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($sender instanceof Player){
			switch($args[0] ?? "x"){
				case "생성":
					$sender->sendForm(new CreateForm());
					break;
				case "제거":
					$sender->sendForm(new RemoveForm());
					break;
				default:
					$sender->sendMessage(Texter::$prefix . "/texter 생성");
					$sender->sendMessage(Texter::$prefix . "/texter 제거");
			}
		}
		return true;
	}
}
