<?php


namespace App\Repo;


use App\Models\Channel;
use App\Models\RequestStat;
use App\Models\Whitelist;
use Illuminate\Support\Collection;

class WhitelistRepository
{

    /**
     * @param string $id
     * @return Channel|null
     */
    public function getChannel(string $id): ?Channel {
        return Channel::whereId($id)->first();
    }

    /**
     * @param Channel $channel
     * @param string $type
     * @param string $id
     * @return mixed
     */
    public function getWhitelist(Channel $channel, string $type, string $id) {
        $key = $type . '-' . $id;

        $stat = new RequestStat;
        $stat->channel()->associate($channel);
        $stat->save();
        $channel->requests++;

        if (app('cache')->tags($id)->has($key)) {
            if ($channel->whitelist_dirty) {
                app('cache')->tags($id)->flush();
            } else {
                $channel->save();
                return app('cache')->tags($id)->get($key);
            }
        }
        $channel->whitelist_dirty = false;
        $channel->save();

        $whitelist = $channel->whitelist()->with('minecraft')->get();
        $list = $this->$type($whitelist);

        app('cache')->tags($id)->put($key, $list, 1800);
        return $list;
    }



    /**
     *
     * Process to filter and map values
     *
     */

    /**
     * A general process of valid subscriptions and mapping names
     * @param Collection $list
     * @return array
     */
    private function process(Collection $list): array
    {
        return $list->filter([$this, 'filterValid'])->map([$this, 'mapUsername'])->toArray();
    }

    /**
     * Base process for valid subscriptions and a valid minecraft name
     * @param Collection $list
     * @return Collection
     */
    private function minecraftProcess(Collection $list): Collection
    {
        return $list->filter([$this, 'filterValid'])->filter([$this, 'filterMinecraft']);
    }

    /**
     * Process to filter and get all valid minecraft uuids
     * @param Collection $list
     * @return array
     */
    private function minecraftUuidProcess(Collection $list): array
    {
        return $this->minecraftProcess($list)->map([$this, 'mapMinecraftUuid'])->flatten()->toArray();
    }

    /**
     * Process to filter and get all valid minecraft usernames
     * @param Collection $list
     * @return array
     */
    private function minecraftNameProcess(Collection $list): array
    {
        return $this->minecraftProcess($list)->map([$this, 'mapMinecraftName'])->flatten()->toArray();
    }

    /**
     * Process to filter and get all valid minecraft uuids and names in the vanilla whitelist format
     * @param Collection $list
     * @return array
     */
    private function minecraftWhitelistProcess(Collection $list): array
    {
        return $this->minecraftProcess($list)->map([$this, 'mapMinecraftWhitelist'])->values()->toArray();
    }

    /**
     * Process to filter valid subscriptions and connected steam accounts, then mapped all ids
     * @param Collection $list
     * @return array
     */
    private function steamProcess(Collection $list): array
    {
        return $list->filter([$this, 'filterValid'])->filter([$this, 'filterSteam'])->map([$this, 'mapSteam'])->flatten()->toArray();
    }



    /**
     *
     * Value filtering
     *
     */

    /**
     * Filter out only valid subscriptions
     * @param Whitelist $value
     * @return bool
     */
    public function filterValid(Whitelist $value): bool
    {
        return $value->valid;
    }

    /**
     * Filter out only whitelists with minecraft names
     * @param Whitelist $value
     * @return bool
     */
    public function filterMinecraft(Whitelist $value): bool
    {
        return !is_null($value->minecraft);
    }

    /**
     * Filter out only whitelists with steam connection
     * @param Whitelist $value
     * @return bool
     */
    public function filterSteam(Whitelist $value): bool
    {
        return !is_null($value->steam);
    }



    /**
     *
     * Value mapping
     *
     */

    /**
     * Maps a whitelist entry to the general username
     * @param Whitelist $value
     * @return string
     */
    public function mapUsername(Whitelist $value): string
    {
        return $value->username;
    }

    /**
     * Maps a whitelist entry to the minecraft uuid
     * @param Whitelist $value
     * @return string
     */
    public function mapMinecraftUuid(Whitelist $value): string
    {
        return $value->minecraft->uuid;
    }

    /**
     * Maps a whitelist entry to the minecraft username
     * @param Whitelist $value
     * @return string
     */
    public function mapMinecraftName(Whitelist $value): string
    {
        return $value->minecraft->username;
    }

    /**
     * Maps a whitelist entry to the minecraft whitelist format
     * @param Whitelist $value
     * @return array
     */
    public function mapMinecraftWhitelist(Whitelist $value): array
    {
        return ['uuid' => $value->minecraft->uuid, 'name' => $value->minecraft->username];
    }

    /**
     * Maps a whitelist entry to the steam id
     * @param Whitelist $value
     * @return string
     */
    public function mapSteam(Whitelist $value): string
    {
        return $value->steam->steam_id;
    }



    /**
     *
     * List assembly
     *
     */

    /**
     * Formats the general usernames to a csv string
     * @param Collection $list
     * @return string
     */
    public function csv(Collection $list): string
    {
        return join(',', $this->process($list));
    }

    /**
     * Formats the general usernames to a newline string
     * @param Collection $list
     * @return string
     */
    public function nl(Collection $list): string
    {
        return join("\n", $this->process($list));
    }

    /**
     * Formats the general usernames to a json array string
     * @param Collection $list
     * @return string
     */
    public function json_array(Collection $list): string
    {
        return json_encode($this->process($list));
    }

    /**
     * Formats the minecraft uuids to a csv string
     * @param Collection $list
     * @return string
     */
    public function minecraft_uuid_csv(Collection $list): string
    {
        return join(',', $this->minecraftUuidProcess($list));
    }

    /**
     * Formats the minecraft uuids to a newline string
     * @param Collection $list
     * @return string
     */
    public function minecraft_uuid_nl(Collection $list): string
    {
        return join("\n", $this->minecraftUuidProcess($list));
    }

    /**
     * Formats the minecraft uuids to a json array string
     * @param Collection $list
     * @return string
     */
    public function minecraft_uuid_json_array(Collection $list): string
    {
        return json_encode($this->minecraftUuidProcess($list));
    }

    /**
     * Formats the minecraft usernames to a csv string
     * @param Collection $list
     * @return string
     */
    public function minecraft_csv(Collection $list): string
    {
        return join(',', $this->minecraftNameProcess($list));
    }

    /**
     * Formats the minecraft usernames to a newline string
     * @param Collection $list
     * @return string
     */
    public function minecraft_nl(Collection $list): string
    {
        return join("\n", $this->minecraftNameProcess($list));
    }

    /**
     * Formats the minecraft usernames to a json array string
     * @param Collection $list
     * @return string
     */
    public function minecraft_json_array(Collection $list): string
    {
        return json_encode($this->minecraftNameProcess($list));
    }

    /**
     * Formats the minecraft usernames and uuids to a json array string
     * @param Collection $list
     * @return string
     */
    public function minecraft_whitelist(Collection $list): string
    {
        return json_encode($this->minecraftWhitelistProcess($list));
    }

    /**
     * Formats the steam ids to a csv string
     * @param Collection $list
     * @return string
     */
    public function steam_csv(Collection $list): string
    {
        return join(',', $this->steamProcess($list));
    }

    /**
     * Formats the steam ids to a newline string
     * @param Collection $list
     * @return string
     */
    public function steam_nl(Collection $list): string
    {
        return join("\n", $this->steamProcess($list));
    }

    /**
     * Formats the steam ids to a json array string
     * @param Collection $list
     * @return string
     */
    public function steam_json_array(Collection $list): string
    {
        return json_encode($this->steamProcess($list));
    }

}
