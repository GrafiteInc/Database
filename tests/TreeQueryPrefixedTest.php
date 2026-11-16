<?php

require_once __DIR__ . '/TreeQueryTest.php';

/**
 * Re-runs the tree suite with a table prefix to prove identifiers are
 * wrapped through the grammar rather than hard-coded.
 */
class TreeQueryPrefixedTest extends TreeQueryTest
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.connections.tree.prefix', 'gr_');
    }
}
