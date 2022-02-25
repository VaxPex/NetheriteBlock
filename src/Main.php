<?php

declare(strict_types=1);

namespace VaxPex;

use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockToolType;
use pocketmine\event\Listener;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use Webmozart\PathUtil\Path;

class Main extends PluginBase implements Listener
{

	protected function onEnable(): void
	{
		if (!is_file($this->getDataFolder() . "creativeitems.json")) {
			$this->saveResource("creativeitems.json");
		}

		$runtimeBlockMapping = RuntimeBlockMapping::getInstance();
		$legacyIdMap = json_decode(file_get_contents(\pocketmine\BEDROCK_DATA_PATH . "block_id_map.json"), true);
		$metaMap = [];

		foreach ($runtimeBlockMapping->getBedrockKnownStates() as $runtimeId => $state) {
			$name = $state->getString("name");
			if (!isset($legacyIdMap[$name])) {
				continue;
			}

			$legacyId = $legacyIdMap[$name];
			if ($legacyId <= 469) {
				continue;
			} elseif (!isset($metaMap[$legacyId])) {
				$metaMap[$legacyId] = 0;
			}

			$meta = $metaMap[$legacyId]++;
			if ($meta > 15) {
				continue;
			}

			$ref = new \ReflectionMethod($runtimeBlockMapping, "registerMapping");
			$ref->setAccessible(true);
			$ref->invoke($runtimeBlockMapping, $runtimeId, $legacyId, $meta);
		}

		StringToItemParser::getInstance()->registerBlock("netherite_block", fn() => BlockFactory::getInstance()->get(525, 0));

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$itemId = -270;
		$blockId = 525;
		BlockFactory::getInstance()->register(new Block(new BlockIdentifier($blockId, 0, $itemId), "Block of Netherite", new BlockBreakInfo(50, BlockToolType::PICKAXE, 5, 6000)));
		$creativeItems = json_decode(file_get_contents(Path::join(\pocketmine\BEDROCK_DATA_PATH, "creativeitems.json")), true);
		foreach ($creativeItems as $data) {
			$item = Item::jsonDeserialize($data);
			if ($item->getName() === "Unknown") {
				continue;
			}
			CreativeInventory::getInstance()->remove($item);
		}
		$creativeItems = json_decode(file_get_contents(Path::join($this->getDataFolder(), "creativeitems.json")), true);
		foreach ($creativeItems as $data) {
			$item = Item::jsonDeserialize($data);
			if ($item->getName() === "Unknown") {
				continue;
			}
			CreativeInventory::getInstance()->add($item);
		}
	}

	//TODO: onChunkLoad to fix it from turning to reversed6(255)

	public function getFullBlock(World $world, int $x, int $y, int $z): int
	{
		return $world->getChunk($x >> 4, $z >> 4)->getFullBlock($x & 0x0f, $y, $z & 0x0f);
	}

	public function sendBlocks(World $world, array $target, array $blocks, int $flags = UpdateBlockPacket::FLAG_NONE, bool $optimizeRebuilds = false): void
	{
		$packets = [];
		if ($optimizeRebuilds) {
			$chunks = [];
			foreach ($blocks as $b) {
				if (!($b->getPosition()->asVector3() instanceof Vector3)) {
					throw new \TypeError("Expected Vector3 in blocks array, got " . (is_object($b) ? get_class($b) : gettype($b)));
				}
				$pk = new UpdateBlockPacket();

				$first = false;
				if (!isset($chunks[$index = World::chunkHash($b->x >> 4, $b->z >> 4)])) {
					$chunks[$index] = true;
					$first = true;
				}

				$pk->blockPosition = new BlockPosition($b->getPosition()->x, $b->getPosition()->y, $b->getPosition()->z);

				if ($b instanceof Block) {
					$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId($b->getFullId());
				} else {
					$fullBlock = $this->getFullBlock($world, $b->getPosition()->x, $b->getPosition()->y, $b->getPosition()->z);
					$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId(BlockFactory::getInstance()->get($fullBlock >> 4, $fullBlock & 0xf)->getFullId());
				}

				$pk->flags = $first ? $flags : UpdateBlockPacket::FLAG_NONE;

				$packets[] = $pk;
			}
		} else {
			foreach ($blocks as $b) {
				if (!($b->getPosition()->asVector3() instanceof Vector3)) {
					throw new \TypeError("Expected Vector3 in blocks array, got " . (is_object($b) ? get_class($b) : gettype($b)));
				}
				$pk = new UpdateBlockPacket();

				$pk->blockPosition = new BlockPosition($b->getPosition()->x, $b->getPosition()->y, $b->getPosition()->z);

				if ($b instanceof Block) {
					$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId($b->getFullId());
				} else {
					$fullBlock = $this->getFullBlock($world, $b->getPosition()->x, $b->getPosition()->y, $b->getPosition()->z);
					$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId(BlockFactory::getInstance()->get($fullBlock >> 4, $fullBlock & 0xf)->getFullId());
				}

				$pk->flags = $flags;

				$packets[] = $pk;
			}
		}

		$this->getServer()->broadcastPackets($target, $packets);
	}
}
