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
		$this->saveResource("creativeitems.json");
		$legacyIdMap = json_decode(file_get_contents(\pocketmine\BEDROCK_DATA_PATH . "block_id_map.json"), true);
		$metaMap = [];
		foreach (RuntimeBlockMapping::getInstance()->getBedrockKnownStates() as $runtimeId => $state) {
			if (!isset($legacyIdMap[$state->getString("name")])) {
				continue;
			}

			if ($legacyIdMap[$state->getString("name")] <= 469) {
				continue;
			} elseif (!isset($metaMap[$legacyIdMap[$state->getString("name")]])) {
				$metaMap[$legacyIdMap[$state->getString("name")]] = 0;
			}

			$meta = $metaMap[$legacyIdMap[$state->getString("name")]]++;
			if ($meta > 15) {
				continue;
			}

			$ref = new \ReflectionMethod(RuntimeBlockMapping::getInstance(), "registerMapping");
			$ref->setAccessible(true);
			$ref->invoke(RuntimeBlockMapping::getInstance(), $runtimeId, $legacyIdMap[$state->getString("name")], $meta);
		}
		StringToItemParser::getInstance()->registerBlock("netherite_block", fn() => BlockFactory::getInstance()->get(525, 0));
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		BlockFactory::getInstance()->register(new Block(new BlockIdentifier(525, 0, -270), "Block of Netherite", new BlockBreakInfo(50, BlockToolType::PICKAXE, 5, 1.200)));
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
}
