<?php
declare(strict_types=1);
namespace aris;

use pocketmine\block\Block;
use pocketmine\block\BlockToolType;
use pocketmine\block\Solid;
use pocketmine\item\Item;
use pocketmine\item\TieredTool;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\tile\Tile;
use pocketmine\tile\Container;
use aris\utils\Facing;

use room17\SkyBlock\SkyBlock;

class Hopper extends Solid{
	protected $id = self::HOPPER_BLOCK;
	protected $itemId = Item::HOPPER;
	private $is_vip = false;
	public function __construct(int $meta = 0){
		$this->meta = $meta;
	}
	
	public function getName() : string{
		return $this->isVip() ? "VIP Hopper" : "Normal Hopper";
	}
	
	public function getVariantBitmask() : int{
		return 0;
	}
	
	public function isTransparent() : bool{
		return true;
	}
	
	public function getLightFilter() : int{
		return 0;
	}
	
	public function getToolType() : int{
		return BlockToolType::TYPE_PICKAXE;
	}
	
	public function getToolHarvestLevel() : int{
		return TieredTool::TIER_WOODEN;
	}
	
	public function isVip() : bool{
		return $this->is_vip;
	}
	
	public function setVip(bool $is_vip = true) : void{
		$this->is_vip = $is_vip;
	}
	
	public function check4Vip(){
		if($this->level === Server::getInstance()->getDefaultLevel()){
			$this->setVip(true);
			return;
		}
		$sessions = SkyBlock::getInstance()->getSessionManager()->getSessions();
		foreach($sessions as $p => $session){
			if($session->hasIsland()){
				if($session->getIsland()->getLevel() == $this->level){
					$player = Server::getInstance()->getPlayer($p);
					if($player === null) $player = Server::getInstance()->getOfflinePlayer($p);
					if($player->hasPermission("hopper.vip") or $player->isOp()){
						$this->setVip(true);
					}else{
						$this->setVip(false);
					}
					break;
				}
			}else{
			}
		}
	}
	
	public function getInventory() : ? HopperInventory{
		return $this->getTile()->getInventory();
	}
	
	public function getContainerAbove() : ? Container{
		$above = $this->level->getTileAt($this->x, $this->y + 1, $this->z);
		return $above instanceof Container ? $above : null;
	}
	
	public function getContainerFacing() : ?Container{
		$facing_pos = $this->getSide($this->getFace());
		$facing = $this->level->getTile($facing_pos);
		return $facing instanceof Container ? $facing : null;
	}
	
	public function place(Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, Player $player = null) : bool{
		if(Facing::opposite($face) == Facing::UP || $face == Facing::UP) $face = Facing::DOWN;
		else $face = Facing::opposite($face); //Have no idea why the facing is in opposite face
		$this->setDamage($face);
		$this->level->setBlock($this, $this);
		$this->check4Vip();
		$nbt = HopperTile::createNBT($this);
		$nbt->setString("CustomName", $this->getName());
		$nbt->setInt("isVip", intval($this->isVip()));
		Tile::createTile("HopperTile", $this->level, $nbt);
		return true;
	}
	
	public function onActivate(Item $item, Player $player = null) : bool{
		$inventory = $this->getInventory();
		$player->addWindow($inventory);
		return true;
	}
	
	public function getTile() : HopperTile{
		$tile = $this->level->getTile($this);
		return $tile instanceof HopperTile ? $tile : Tile::createTile("HopperTile", $this->level, HopperTile::createNBT($this));
	}
	
	public function getFace() : int{
		return $this->getDamage();
	}
}