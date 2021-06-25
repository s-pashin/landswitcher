<?php declare(strict_types=1);


namespace Plugin\landswitcher;


use JTL\Events\Dispatcher;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    /**
     * @Document inheritance
     */
    public function boot(Dispatcher $dispatcher)
    {
        parent::boot($dispatcher);

        if (Shop::isFrontend() === false) {
            return;
        }

        $lang = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'])[0];
        $langParts = explode('-', $lang);
        $countryCode = $langParts[1] ?? $langParts[0];

        $db = $this->getDB();
        $linksHandler = new LinksHandler($db);
        $url = $linksHandler->getUrl($countryCode);

        if (!empty($url)) {
            header("Location: {$url}", true, 301);
            die();
        }
    }

    /**
     * @Document inheritance
     */
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $db = $this->getDB();
        $linksHandler = new LinksHandler($db);

        if (Form::validateToken()) {
            $requestLinks = Request::postVar('links');
            if ($linksHandler->validate($requestLinks)) {
                $linksHandler->sync($requestLinks);
            }
        }

        $links = $linksHandler->getFromDb();
        $countries = $db->selectAll('tland', [], [], 'cISO, cEnglisch', 'cEnglisch');

        $templatePath = $this->getPlugin()->getPaths()->getAdminPath() . 'template/';
        $rowTemplate = $smarty->assign('countries', $countries)->fetch($templatePath . 'link_row.tpl');

        return $smarty->assign('links', $links)
            ->assign('rowTemplate', $rowTemplate)
            ->fetch($templatePath . 'links.tpl');
    }
}
