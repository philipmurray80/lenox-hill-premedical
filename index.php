<?php echo 'hello world'; ?>
//
// /**
//  * Step 1: Require the Slim Framework
//  *
//  * If you are not using Composer, you need to require the
//  * Slim Framework and register its PSR-0 autoloader.
//  *
//  * If you are using Composer, you can skip this step.
//  */
//
// require 'Slim/Slim.php';
//
// \Slim\Slim::registerAutoloader();
//
// /**
//  * Step 2: Instantiate a Slim application
//  *
//  * This example instantiates a Slim application using
//  * its default settings. However, you will usually configure
//  * your Slim application now by passing an associative array
//  * of setting names and values into the application constructor.
//  */
// $app = new \Slim\Slim(array(
//     'view' => '\Slim\ViewWithLayout',
//     'cookies.encrypt' => true,
//     'stripe_secret_key' => getenv('STRIPE_SECRET_KEY'),
//     'stripe_publishable_key' => getenv('STRIPE_PUBLISHABLE_TEST_KEY'),
//     'debug' => false,
//     'mode' => 'production',
//     'log.enabled' => true
// ));
//
// $app->add(new \Slim\Middleware\SessionCookie(array(
//     'secret' => getenv('SLIM_COOKIE_SECRET'),
//     'httponly' => true,
//     'name' => 'lenox_hill_premedical_session',
//     'expires' => '100 minutes' //time between clicks.
// )));
//
//
// //Phil - Register PDO factory
// $app->container->singleton('db', function () {
//     $dsn = getenv('MYSQL_DSN');
//     $user = getenv('MYSQL_USER');
//     $password = getenv('MYSQL_PASSWORD');
//     $db = new \PDO($dsn, $user, $password);
//     return $db;
// });
// /**
//  * Step 3: Define the Slim application routes
//  *
//  * Here we define several Slim application routes that respond
//  * to appropriate HTTP request methods. In this example, the second
//  * argument for `Slim::get`, `Slim::post`, `Slim::put`, `Slim::patch`, and `Slim::delete`
//  * is an anonymous function.
//  */
//
// $app->map(
//     '/writer(/:action)',
// //If they are not logged in, deny access
//     function () use ($app) {
//         if (!isset($_SESSION['user_id'])) {
//             $app->redirect('/');
//         }
//     },
//
// //If they are not an administrator, or writer, log them out.
//     function () use ($app) {
//         if ($_SESSION['role'] != 'writer' && $_SESSION['role'] != 'admin') {
//             unset($_SESSION);
//             $app->redirect('/user/login');
//         }
//     },
// //Set the appropriate layout
//     function () use ($app) {
//         $app->view->setLayout('writer-layout.phtml');
//     },
// //Initialize the Writer Controller
//     function ($action = 'index') use ($app) {
//         $controller = new Controller\WriterController();
//         $controller->setApp($app);
//         $controller->dispatchAction($action);
//     }
// )->via('GET', 'POST');
//
// $app->map(
//     '/admin(/:action)',
//     //If they are not logged in, deny access
//     function () use ($app) {
//         if (!isset($_SESSION['user_id'])) {
//             $app->redirect('/');
//         }
//     },
//     //If they are not an administrator, log them out
//     function () use ($app) {
//         if ($_SESSION['role'] != 'admin') {
//             unset($_SESSION);
//             $app->redirect('/user/login');
//         }
//     },
//     //Set the appropriate layout.
//     function () use ($app) {
//         $app->view->setLayout('admin-layout.phtml');
//     },
//
//     //Initialize the Admin Controller
//     function ($action = 'index') use ($app) {
//         $controller = new Controller\AdminController();
//         $controller->setApp($app);
//         $controller->dispatchAction($action);
//     }
// )->via('GET', 'POST');
//
// $app->map(
//     '/user(/:action)',
//     //added by Phil on 9/23/2016
//     /*function () use ($app) {if (isset($_SESSION['user_id']) && !in_array($_SESSION['user_id'], array(25,34,56,26,64))) {
//         unset($_SESSION);
//         $app->redirect('/');}},*/
//     function () use ($app) {
//         if (isset($_SESSION['user_id'])) {
//             $app->view->setLayout('user-layout.phtml');
//         } else {
//             $app->view->setLayout('layout.phtml');
//         }
//     }, //route middleware to set layout
//     function ($action = 'index') use ($app) {
//         $controller = new Controller\UserController();
//         $controller->setApp($app);
//         $controller->dispatchAction($action);
//     }
// )->via('GET', 'POST');
//
// $app->map(
//     '/(:action)',
//     //addded by Phil on 9/23/2016
//     /*function () use ($app) {if (isset($_SESSION['user_id']) && !in_array($_SESSION['user_id'], array(25,34,56,26,64))) {
//         unset($_SESSION);
//         $app->redirect('/');}},*/
//     function () use ($app) {
//         if (isset($_SESSION['user_id'])) {
//             $app->view->setLayout('user-layout.phtml');
//         } else {
//             $app->view->setLayout('layout.phtml');
//         }
//     }, //route middleware to set layout
//     function ($action = 'index') use ($app) {
//         $controller = new Controller\IndexController();
//         $controller->setApp($app);
//         $controller->dispatchAction($action);
//     }
// )->via('GET', 'POST');
//
// $app->map(
//     '/full-length/:pageType/:examId/:fullLengthNumber/:pageNumber',
//     function () use ($app) {
//         if (!isset($_SESSION['user_id'])) {
//             $app->redirect('/');
//         }
//     },
//     function ($pageType, $examId, $fullLengthNumber, $pageNumber) use ($app) {
//
//         //Validate that query parameters are all integers.
//         $examId = filter_var($examId, FILTER_VALIDATE_INT);
//         $fullLengthNumber = filter_var($fullLengthNumber, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 5)));
//         $pageNumber = filter_var($pageNumber, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 100)));
//
//         //Log them out if any of the validators fail.
//         if (!$examId || !$fullLengthNumber || !$pageNumber) {
//             unset($_SESSION);
//             $app->redirect('/');
//         }
//
//         //If its a post, validate post data.
//         if ($app->request->isPost()) {
//             $post = $app->request->post();
//             //white list of every possible post parameter key that is not numeric
//             $whiteListKeys = array('timeRemaining', 'next', 'previous', 'iAgree', 'endSection', 'end', 'review', 'reviewAll', 'reviewMarked', 'reviewIncomplete', 'void', 'yes', 'annotation', 'annotationCount', 'annotationChanged');
//
//             //white list of every possible post parameter value that is not numeric
//             $whiteListValues = array('NULL', 'NEXT', 'END TUTORIAL', 'END BREAK', 'PREVIOUS', 'I AGREE', 'END SECTION', 'REVIEW', 'REVIEW ALL', 'REVIEW MARKED', 'REVIEW INCOMPLETE', 'VOID', 'SCORE', 'BEGIN EXAM', 'FALSE', 'TRUE');
//             echo '<br/><br/><br/><br/>';
//             var_dump($post);
//             //Strategy: If the key is numeric, let the contentPageAction handle it. If it is not numeric,
//             //1)Make sure the key is on the key white list.
//             //2)Except for annotations, make sure the value is either numeric or on the value white list.
//             foreach ($post as $key => $value) {
//                 if (!is_numeric($key)) {
//                     if (!in_array($key, $whiteListKeys)) {
//                         unset($_SESSION);
//                         $app->redirect('/');
//                     }
//                     if ($key == 'annotation') {
//                         //filter annotations
//                         $allowedTags = '<p><b><em><i><ul><ol><li><small><figure><figcaption>';
//                         $allowedTags .= '<br><br/><img><span><sup><sub><h3><strong>';
//                         $allowedTags .= '<table><caption><thead><tbody><td><tr><th>';
//                         if ($post['annotation'] != strip_tags($post['annotation'], $allowedTags)) {
//                             $app->flash('message', 'some tags were uploaded that didnt pass the filter');
//                             $app->redirect('/user');
//                         }
//                     } elseif (!(in_array($value, $whiteListValues) || is_numeric($value))) {
//                         unset($_SESSION);
//                         $app->redirect('/');
//                     }
//                 }
//             }
//         }
//
//         //Make sure this exam belongs to the user and retrieve its status.
//         $sql = 'SELECT Status FROM exams WHERE ExamId = :examId AND UserId = :userId';
//         $stmt = $app->db->prepare($sql);
//         $stmt->execute(array(':examId' => $examId, ':userId' => $_SESSION['user_id']));
//         $result = $stmt->fetch(\PDO::FETCH_ASSOC);
//
//         if ($result === false) {//user using someone else's exam
//             unset($_SESSION);
//             $app->redirect('/');
//         }
//         $status = $result['Status'];
//
//         //Make sure the exam has not expired.
//         $sql = 'SELECT ExpDate FROM full_length_access WHERE UserId = :userId AND FullLengthNumber = :fullLengthNumber';
//         $stmt = $app->db->prepare($sql);
//         $stmt->execute(array(':userId' => $_SESSION['user_id'], ':fullLengthNumber' => $fullLengthNumber));
//         $result = $stmt->fetch(\PDO::FETCH_ASSOC);
//         $now = new \DateTime('now', new DateTimeZone('America/New_York'));
//         if ($result['ExpDate'] < $now->format('Y-m-d H:i:s')) { //Exam has expired. Redirect them to user homepage.
//             $app->redirect('/user');
//         }
//
//         //If the exam has not yet been completed, determine if they are making an allowed page turn;
//         if ($status == 'incomplete') {
//             $sql = 'SELECT CurrentPageNumber FROM exams WHERE ExamId = :examId';
//             $stmt = $app->db->prepare($sql);
//             $stmt->execute(array(':examId' => $examId));
//             $currentPageNumber = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['CurrentPageNumber'];
//             if ($pageNumber < $currentPageNumber) {//They're trying to access an earlier page
//                 $sql = 'SELECT PageType, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :currentPageNumber ';
//                 $sql .= 'UNION SELECT PageType, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber';
//                 $stmt = $app->db->prepare($sql);
//                 $stmt->execute(array(':fullLengthNumber' => $fullLengthNumber,':currentPageNumber' => $currentPageNumber, ':pageNumber' => $pageNumber));
//                 $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
//                 if (count($result) != 1) {//The page they're trying to access does not have same pageType and Section as the page they're currently on.
//                     if (!($result[0]['PageType'] == 'review' && ($result[0]['Section'] == $result[1]['Section']))) {
//                         //They're trying to access a previous exam page that they're not supposed to. Nothing malicious here. They might have just clicked the back button.
//                         //Redirect them to the page they were just on.
//                         $app->redirect('/full-length/'.$result[0]['PageType'].'-page/'.$examId.'/'.$fullLengthNumber.'/'.$currentPageNumber);
//                     }
//                 }
//             }
//             $app->view->setLayout('full-length-layout.phtml');
//         } elseif ($status == 'scored' && $pageType == 'finish-page') {//this path will be taken only after void page scores exam and redirects to finish page.
//             $app->view->setLayout('full-length-layout.phtml');
//         } else {
//             //Make sure the only pagetypes they can access are content, directions, and review.
//             if (!in_array($pageType, array('review-content-page', 'review-directions-page', 'review-review-page'))) {
//                 unset($_SESSION);
//                 $app->redirect('/');
//             }
//             $app->view->setLayout('full-length-review-layout.phtml');
//         }
//
//         // Check to see if you need to update their current_full_length status
//         if ($_SESSION['cfl'] != $fullLengthNumber) {
//             $sql = 'UPDATE user SET current_full_length = :fullLengthNumber WHERE user_id = :userId';
//             $stmt = $app->db->prepare($sql);
//             $stmt->execute(array(':fullLengthNumber' => $fullLengthNumber, ':userId' => $_SESSION['user_id']));
//             $_SESSION['cfl'] = $fullLengthNumber;
//         }
//
//         //Inject the proctor controller with its parameters.
//         $controller = new Controller\ProctorController();
//         $controller->setApp($app);
//         $controller->examId = $examId;
//         $controller->fullLengthNumber = $fullLengthNumber;
//         $controller->pageNumber = $pageNumber;
//         $controller->status = $status;
//         $controller->dispatchAction($pageType);
//     }
// )->via('GET', 'POST');
//
//
//
// /**
//  * Step 4: Run the Slim application
//  *
//  * This method should be called last. This executes the Slim application
//  * and returns the HTTP response to the HTTP client.
//  */
// $app->run();
