<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\RouteList;


final class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;
//        $router->withModule('Admin')
//            ->addRoute('<presenter>/<action>', ':Dashboard:default');

//        $router->addRoute('Modules/admin/<presenter>/<action>', 'Dashboard:default');

/*        $router->addRoute('admin/<presenter>/<action>', [
            'module' => 'Admin',
            'presenter' => 'Dashboard',
            'action' => 'test',
        ]);*/

        $router->addRoute('admin', 'Admin:Dashboard:default');
        $router->addRoute('slozky-pripravku', 'Composition:default');
        $router->addRoute('muzska-neplodnost', 'Infertility:default');
        $router->addRoute('vyzkum', 'Research:default');
        $router->addRoute('poradime-vam', 'Advise:default');
        $router->addRoute('kontakt', 'Contact:default');
        // En
        $router->addRoute('homepage', 'Homepage:home');
        $router->addRoute('components-of-the-preparation', 'Composition:components');
        $router->addRoute('male-infertility', 'Infertility:infertility');
        $router->addRoute('research', 'Research:research');
        $router->addRoute('advise', 'Advise:advise');
        $router->addRoute('contact', 'Contact:contact');

        $router->addRoute('admin/<presenter>/<action>', [
            'module' => 'Admin',
            'presenter' => 'Dashboard',
            'action' => 'default',
        ]);



//        $router->addRoute('Modules/Admin/<presenter>/<action>[/<id>]', 'Admin:Dashboard:default');
		$router->addRoute('<presenter>/<action>[/<id>]', 'Homepage:default');
		return $router;
	}
}
