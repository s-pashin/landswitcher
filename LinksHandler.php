<?php declare(strict_types=1);


namespace Plugin\landswitcher;


use JTL\Alert\Alert;
use JTL\DB\DbInterface;
use JTL\Shop;
use stdClass;

class LinksHandler
{
    /**
     * @var DbInterface
     */
    private $db;

    /**
     * @var string
     */
    private $table = 'landswitcher_links';

    /**
     * LinksHandler constructor.
     * @param DbInterface $db
     */
    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /**
     * @param array $requestData
     * @return bool
     */
    public function validate(array $requestData): bool
    {
        $invalidLinks = array_filter($requestData, function ($link) {
            return $this->isInvalid($link);
        });
        if (!empty($invalidLinks)) {
            $alerts = Shop::Container()->getAlertService();
            $alerts->addAlert(
                Alert::TYPE_ERROR,
                'Check the correctness of filling in the fields',
                'links_validate'
            );

            return false;
        }

        return true;
    }

    /**
     * @param array $requestData
     */
    public function sync(array $requestData): void
    {
        $links = $this->getFromDb();
        foreach ($requestData as $id => $dataItem) {
            $newLink = new \stdClass();
            $newLink->country = $dataItem['country'];
            $newLink->url = $dataItem['url'];
            $newLink->active = $dataItem['active'] ? 1 : 0;

            $curLink = array_values(array_filter($links, function ($link) use ($id) {
                return $link->id == $id;
            }))[0] ?? null;

            if (is_null($curLink)) {
                $this->db->insert($this->table, $newLink);
            } elseif ($this->isChanged($newLink, $curLink)) {
                $this->db->update($this->table, 'id', $curLink->id, $newLink);
            }
        }

        foreach ($links as $link) {
            if (!in_array($link->id, array_keys($requestData))) {
                $this->delete($link->id);
            }
        }
    }

    /**
     * @param string|int $id
     * @return int
     */
    public function delete($id): int
    {
        return $this->db->delete($this->table, 'id', $id);
    }

    /**
     * @return array
     */
    public function getFromDb(): array
    {
        return $this->db->selectAll($this->table, [], [], '*', 'id');
    }

    /**
     * @param string $country
     * @return string
     */
    public function getUrl(string $country): string
    {
        return $this->db->selectSingleRow($this->table, 'country', mb_strtoupper($country))->url ?? '';
    }

    /**
     * @param array $link
     * @return bool
     */
    private function isInvalid(array $link): bool
    {
        return empty($link['country']) || empty($link['url']);
    }

    /**
     * @param stdClass $newLink
     * @param stdClass $curLink
     * @return bool
     */
    private function isChanged(stdClass $newLink, stdClass $curLink): bool
    {
        return $curLink->country != $newLink->country
            || $newLink->url != $curLink->url
            || $newLink->active != $curLink->active;
    }
}
