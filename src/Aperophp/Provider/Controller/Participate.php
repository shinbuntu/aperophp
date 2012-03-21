<?php

namespace Aperophp\Provider\Controller;

use Aperophp\Model;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 *  Controller for DrinkParticipations managing.
 *
 *  @author Gautier DI FOLCO <gautier.difolco@gmail.com>
 *  @version 1.3 - 21 mars 2012 - Gautier DI FOLCO <gautier.difolco@gmail.com>
 */
class Participate implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        // TODO : Send mail
        /*
        $message = \Swift_Message::newInstance()
            ->setSubject('[YourSite] Feedback')
            ->setFrom(array('noreply@yoursite.com'))
            ->setTo(array('feedback@yoursite.com'))
            ->setBody($request->get('message'));

        $app['mailer']->send($message);
         */

        // *******
        // ** Save/Update participation
        // *******
        $controllers->post('{drink_id}/register.html', function(Request $request, $drink_id) use ($app)
        {
            $oDrink = Model\Drink::findOneById($app['db'], $drink_id);

            if (!$oDrink)
            {
                $app->abort(404, 'Cet événement n\'existe pas.');
            }

            $now = new \Datetime('now');
            $dDrink = \Datetime::createFromFormat(  'Y-m-d H:i:s',
                                                    $oDrink->getDay() . ' ' . $oDrink->getHour());
            if ($now > $dDrink)
            {
                $app['session'] ->setFlash('error', 'L\'événement est terminé.');
                return $app->redirect($app['url_generator']->generate('_showdrink', array('id' => $drink_id)));
            }

            $oUser = null;
            $values = array();
            if ($user = $app['session']->get('user'))
            {
                $oUser = Model\User::findOneById($app['db'], $user['id']);
            }

            $form           = $app['form.factory']->create(new \Aperophp\Form\DrinkParticipationType(), null, array('user' => $oUser));

            $form->bindRequest($request);
            if ($form->isValid())
            {
                $data = $form->getData();

                if ($oUser && $oUser->getId() != $data['user_id'])
                {
                    throw new \Exception('Une erreur est survenue, il se peut que vous vous soyez connecté entre temps');
                }

                if (!$oUser && $data['user_id'])
                {
                    throw new \Exception('Une erreur est survenue, il se peut que vous ayez perdu votre session');
                }

                $app['db']->beginTransaction();

                try
                {
                    // If member is not authenticated, a user is created.
                    if (!$oUser)
                    {
                        $oUser = new Model\User($app['db']);
                        $oUser
                                ->setEmail($data['email'])
                                ->setFirstname($data['firstname'])
                                ->setLastname($data['lastname'])
                                ->save();
                    }

                    $data           = $form->getData();
                    $participation  = Model\DrinkParticipation::find(
                                                                        $app['db'],
                                                                        $drink_id,
                                                                        $oUser->getId()
                                                                    );

                    if( null === $participation )
                    {
                        $participation  = new Model\DrinkParticipation($app['db']);
                        $participation
                                        ->setDrinkId($drink_id)
                                        ->setUserId($oUser->getId());
                    }

                    $participation      ->setPercentage($data['percentage'])
                                        ->setReminder($data['reminder'])
                                        ->save();

                    $app['session']     ->setFlash('success', 'Participation modifiée.');

                    $app['db']->commit();
                }
                catch (Exception $e)
                {
                    $app['db']->rollback();
                    throw $e;
                }

                return $request->isXmlHttpRequest() ? 'redirect' : $app->redirect($app['url_generator']->generate('_showdrink', array('id' => $drink_id)));
            }

            return $app['twig']->render('drink/participate.html.twig', array(
                'participationForm' => $form->createView(),
                'drink' => $oDrink,
            ));

        })->bind('_participatedrink');
        // *******

        // *******
        // ** Delete participation
        // *******
        $controllers->get('{drink_id}/delete.html', function(Request $request, $drink_id) use ($app)
        {
            $oDrink = Model\Drink::findOneById($app['db'], $drink_id);

            if (!$oDrink)
            {
                $app->abort(404, 'Cet événement n\'existe pas.');
            }

            $now = new \Datetime('now');
            $dDrink = \Datetime::createFromFormat(  'Y-m-d H:i:s',
                                                    $oDrink->getDay() . ' ' . $oDrink->getHour());
            if ($now > $dDrink)
            {
                $app['session'] ->setFlash('error', 'L\'événement est terminé.');
                return $app->redirect($app['url_generator']->generate('_showdrink', array('id' => $drink_id)));
            }

            $oUser = null;
            $values = array();
            if ($user = $app['session']->get('user'))
            {
                $oUser = Model\User::findOneById($app['db'], $user['id']);
            }

            $app['db']->beginTransaction();

            try
            {
                // If member is not authenticated, token/email is checked.
/*                if (!$oUser)
                {
                        $app['session'] ->setFlash('error', 'Couple email/jeton invalide.');
                        return $app     ->redirect($app['url_generator']
                                        ->generate('_viewdrink'));
                    $oUser = new Model\User($app['db']);
                    $oUser
                            ->setEmail($data['email'])
                            ->setFirstname($data['firstname'])
                            ->setLastname($data['lastname'])
                            ->save();
                }*/

                $participation  = Model\DrinkParticipation::find(
                                                                    $app['db'],
                                                                    $drink_id,
                                                                    $oUser->getId()
                                                                );

                if( null === $participation )
                    $app['session'] ->setFlash('error', 'Participation inéxistante.');
                else
                {
                    $participation  ->delete();
                    $app['session'] ->setFlash('success', 'Participation supprimée avec succès.');
                }

                $app['session']     ->setFlash('success', 'Participation modifiée.');

                $app['db']->commit();
            }
            catch (Exception $e)
            {
                $app['db']->rollback();
                throw $e;
            }

            return $request->isXmlHttpRequest() ? 'redirect' : $app->redirect($app['url_generator']->generate('_showdrink', array('id' => $drink_id)));


            return $app->redirect($app['url_generator']->generate('_viewdrink'));
        })->bind('_deleteparticipatedrink');

        return $controllers;
    }
}
