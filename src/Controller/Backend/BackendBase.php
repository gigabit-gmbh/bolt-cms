<?php
namespace Bolt\Controller\Backend;

use Bolt\Controller\Base;
use Bolt\Controller\Zone;
use Bolt\Translation\Translator as Trans;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for all backend controllers.
 *
 * @author Carson Full <carsonfull@gmail.com>
 */
abstract class BackendBase extends Base
{
    public function connect(Application $app)
    {
        $c = parent::connect($app);
        $c->value(Zone::KEY, Zone::BACKEND);

        $c->before(array($this, 'before'));

        return $c;
    }

    /**
     * Adds a flash message to the current session for type.
     *
     * @param string $type    The type
     * @param string $message The message
     */
    protected function addFlash($type, $message)
    {
        $this->app['session']->getFlashBag()->add($type, $message);
    }

    protected function render($template, array $variables = array(), array $globals = array())
    {
        if (!isset($variables['context'])) {
            $variables = array('context' => $variables);
        }
        return parent::render($template, $variables, $globals);
    }

    /**
     * Middleware function to check whether a user is logged on.
     *
     * @param Request     $request   The Symfony Request
     * @param Application $app       The application/container
     * @param string      $roleRoute An overriding value for the route name in permission checks
     *
     * @return null|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function before(Request $request, Application $app, $roleRoute = null)
    {
        // Start the 'stopwatch' for the profiler.
        $app['stopwatch']->start('bolt.backend.before');

        $route = $request->get('_route');

        // Handle the case where the route doesn't equal the role
        if ($roleRoute === null) {
            $roleRoute = $route;
        }

        // Sanity checks for doubles in in contenttypes.
        // unfortunately this has to be done here, because the 'translator' classes need to be initialised.
        $app['config']->checkConfig();

        // If we had to reload the config earlier on because we detected a version change, display a notice.
        if ($app['config']->notify_update) {
            $notice = Trans::__("Detected Bolt version change to <b>%VERSION%</b>, and the cache has been cleared. Please <a href=\"%URI%\">check the database</a>, if you haven't done so already.",
                array('%VERSION%' => $app->getVersion(), '%URI%' => $app['resources']->getUrl('bolt') . 'dbcheck'));
            $app['logger.system']->notice(strip_tags($notice), array('event' => 'config'));
            $app['logger.flash']->info($notice);
        }

        // Check the database users table exists
        $tableExists = $app['integritychecker']->checkUserTableIntegrity();

        // Test if we have a valid users in our table
        $hasUsers = false;
        if ($tableExists) {
            $hasUsers = $app['users']->hasUsers();
        }

        // If the users table is present, but there are no users, and we're on /bolt/userfirst,
        // we let the user stay, because they need to set up the first user.
        if ($tableExists && !$hasUsers && $route === 'userfirst') {
            return null;
        }

        // If there are no users in the users table, or the table doesn't exist. Repair
        // the DB, and let's add a new user.
        if (!$tableExists || !$hasUsers) {
            $app['integritychecker']->repairTables();
            $app['logger.flash']->info(Trans::__('There are no users in the database. Please create the first user.'));

            return $this->redirectToRoute('userfirst');
        }

        // Confirm the user is enabled or bounce them
        if ($app['users']->getCurrentUser() && !$app['users']->isEnabled() && $route !== 'userfirst' && $route !== 'login' && $route !== 'postLogin' && $route !== 'logout') {
            $app['logger.flash']->error(Trans::__('Your account is disabled. Sorry about that.'));

            return $this->redirectToRoute('logout');
        }

        // Check if there's at least one 'root' user, and otherwise promote the current user.
        $app['users']->checkForRoot();

        // Most of the 'check if user is allowed' happens here: match the current route to the 'allowed' settings.
        if (!$app['authentication']->isValidSession() && !$app['users']->isAllowed($route)) {
            $app['logger.flash']->info(Trans::__('Please log on.'));

            return $this->redirectToRoute('login');
        } elseif (!$app['users']->isAllowed($roleRoute)) {
            $app['logger.flash']->error(Trans::__('You do not have the right privileges to view that page.'));

            return $this->redirectToRoute('dashboard');
        }

        // Stop the 'stopwatch' for the profiler.
        $app['stopwatch']->stop('bolt.backend.before');

        return null;
    }
}
