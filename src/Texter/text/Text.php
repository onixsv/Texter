<?php
declare(strict_types=1);

namespace Texter\text;

use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\convert\SkinAdapterSingleton;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\FloatMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use Ramsey\Uuid\Uuid;
use Texter\Texter;
use function explode;

class Text{

	protected string $string;

	protected Position $pos;

	protected int $entityId;

	protected \Ramsey\Uuid\UuidInterface $uuid;

	public array $hasSpawned = [];

	protected string $world;

	public function __construct(string $string, Position $pos, string $world){
		$this->string = $string;
		$this->pos = $pos;
		$this->entityId = Entity::nextRuntimeId();
		$this->uuid = Uuid::uuid4();
		$this->world = $world;
	}

	public function hasValidPos() : bool{
		if($this->pos->isValid()){
			return true;
		}
		if(($world = Server::getInstance()->getWorldManager()->getWorldByName($this->world)) !== null){
			$this->pos->world = $world;
			return true;
		}
		return false;
	}

	public function getText() : string{
		return $this->string;
	}

	public function getPosition() : Position{
		return $this->pos;
	}

	public function jsonSerialize() : array{
		return [
			"string" => $this->string,
			"pos" => Texter::toString($this->pos)
		];
	}

	public static function jsonDeserialize(array $data) : Text{
		[, , , $world] = explode(":", $data["pos"]);
		return new Text((string) $data["string"], Texter::toPosition($data["pos"]), $world);
	}

	public function spawnTo(Player $player) : void{
		if(isset($this->hasSpawned[$player->getName()]))
			return;
		if(!$this->pos->isValid()){
			return;
		}
		if($player->getWorld()->getFolderName() !== $this->getPosition()->getWorld()->getFolderName())
			return;

		//$player->getNetworkSession()->sendDataPacket(PlayerListPacket::add([PlayerListEntry::createAdditionEntry($this->uuid, $this->entityId, $this->string, SkinAdapterSingleton:::get()->toSkin(new Skin("Standard_Custom", str_repeat("\x00", 8192))))]));
		$pk = new PlayerListPacket();
		$pk->entries = [
			PlayerListEntry::createAdditionEntry($this->uuid, $this->entityId, $this->string, SkinAdapterSingleton::get()->toSkinData(new Skin("Standard_Custom", str_repeat("\x00", 8192))))
		];
		$pk->type = PlayerListPacket::TYPE_ADD;
		$player->getNetworkSession()->sendDataPacket($pk);
		$pk = new AddPlayerPacket();
		$pk->item = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(ItemFactory::air()));
		$pk->position = $this->pos->floor()->add(0.5, 1, 0.5);
		$pk->actorUniqueId = $pk->actorRuntimeId = $this->entityId;
		$pk->username = str_replace("(줄바꿈)", "\n", $this->string);
		$pk->uuid = $this->uuid;
		$pk->motion = null;

		$pk->metadata = [
			EntityMetadataProperties::FLAGS => new LongMetadataProperty(1 << EntityMetadataFlags::IMMOBILE),
			EntityMetadataProperties::SCALE => new FloatMetadataProperty(0.01)
		];
		$player->getNetworkSession()->sendDataPacket($pk);
		$this->hasSpawned[$player->getName()] = time();
		//$player->getNetworkSession()->sendDataPacket(PlayerListPacket::remove([PlayerListEntry::createRemovalEntry($this->uuid)]));
		$pk = new PlayerListPacket();
		$pk->entries = [
			PlayerListEntry::createRemovalEntry($this->uuid)
		];
		$pk->type = PlayerListPacket::TYPE_REMOVE;
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	public function despawnTo(Player $player) : void{
		if(!isset($this->hasSpawned[$player->getName()]))
			return;
		$pk = new RemoveActorPacket();
		$pk->actorUniqueId = $this->entityId;
		$player->getNetworkSession()->sendDataPacket($pk);
		unset($this->hasSpawned[$player->getName()]);
	}

	public function distance(Position $to) : float{
		return $this->pos->distance($to);
	}
}
