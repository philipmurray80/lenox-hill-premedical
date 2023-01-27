<?php
namespace Controller;

use Slim\Slim;
use Model\McatSection;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


class UserController
{
    public $app;

    public $actions = array(
        'index'           => 'indexAction',
        'login'           => 'loginAction',
        'logout'          => 'logoutAction',
        'register'        => 'registerAction',
        'change-password' => 'changePasswordAction',
        'forgot-password' => 'forgotPasswordAction',
        'reset-password'  => 'resetPasswordAction',
        'delete-exam'     => 'deleteExamAction',
        'provision-exam'  => 'provisionExamAction',
    );

    public function dispatchAction($action)
    {
        $method = $this->actions[$action];
        return $this->$method();
    }

    public function indexAction()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->app->redirect('/user/login');
        }

        if ($this->app->request->isPost()) {
            $this->purchaseExam();
        }

        $expDates = $this->getExpDates($_SESSION['user_id']);
        $now = new \DateTime('now', new \DateTimeZone('America/New_York'));
        $exams = $this->getAllExams($_SESSION['user_id']);
        $stripePublishableKey = $this->app->config('stripe_publishable_key');

        $anonymousStudent = (McatSection::ANONYMOUS_STUDENT_ID == $_SESSION['user_id']) ? true : false;

        $priceInCents = 5000;
        $promoCode = '';
        if (isset($_GET['promoCode'])) {
            if ($_GET['promoCode'] == '') {//they clicked to use a promo code but didn't type anything in.
                $this->app->flash('message', '<span class="text-danger">If you would like to use a promo code, please type it into the box below.</span>');
                $this->app->redirect('/user');
            }
            $sanitizedPromoCode = filter_var($_GET['promoCode'], FILTER_SANITIZE_ENCODED);
            $promoCodeInfo = $this->applyPromoCode($sanitizedPromoCode);
            if (!$promoCodeInfo) {//did not recognize promo code.
                $this->app->flash('message', '<span class="text-danger">The promo code you submitted does not match any that are currently active. Please try again.</span>');
                $this->app->redirect('/user');
            }
            $promoCode = $promoCodeInfo['promo_code'];
            $priceInCents = 5000 * (100 - $promoCodeInfo['promo_code_discount'])/100;
        }

        $this->app->render('user-index.phtml', array(
            'currentFullLength' => $_SESSION['cfl'],
            'now'       => $now,
            'firstName' => $_SESSION['first_name'],
            'exams'     => $exams,
            'expDates'  => $expDates,
            'stripePublishableKey' => $stripePublishableKey,
            'anonymousStudent' => $anonymousStudent,
            'priceInCents'	   => $priceInCents,
            'promoCode'		   => $promoCode
        ));
    }

    public function loginAction()
    {
        //if they're already logged in, send them to user homepage.
        if (isset($_SESSION['user_id'])) {
            $this->app->redirect('/user');
        }

        if ($this->app->request()->isPost()) {
            $email = $this->app->request->post('email');
            $password = $this->app->request->post('credential');

            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            $email = filter_var($email, FILTER_VALIDATE_EMAIL);

            if (!$email) {
                $this->app->flash('errors', array('login' => true));
                $this->app->redirect('/user/login');
            }

            //add salt to password
            $password = 'mcat'.$password.'2015';

            $sql = 'SELECT * FROM user WHERE email = :email AND password = :password'; //todo make sure emails are unique in database
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':email' => $email, ':password' => md5($password)));


            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(\PDO::FETCH_OBJ); //user has been authenticated
                $_SESSION['user_id'] = $user->user_id;
                $_SESSION['first_name'] = $user->first_name;
                $_SESSION['cfl'] = $user->current_full_length;
                $_SESSION['role'] = $user->role;
                $this->app->redirect('/user');
            } else {
                $this->app->flash('errors', array('login' => true));
                $this->app->redirect('/user/login');
            }
        }

        $flash = $this->app->view()->getData('flash');
        $message = null;

        if (isset($flash['message'])) {
            $message = $flash['message'];
        }

        if (isset($flash['errors']['login'])) {
            $message = 'Your Lenox Hill Premedical email/password combination is incorrect. Please try again.';
        }

        if ($this->app->request->get('beginFreeMcat') == 'true') {
            $beginFreeMcat = true;
        } else {
            $beginFreeMcat = false;
        }

        $this->app->render('user-login.phtml', array('message' => $message, 'beginFreeMcat' => $beginFreeMcat));
    }

    public function logoutAction()
    {
        if (isset($_SESSION['user_id']) || isset($_SESSION['first_name'])) {
            unset($_SESSION);
            $this->app->flash('message', '<span style="color:green">You have successfully logged out of your Lenox Hill Premedical account</span>');
        }
        $this->app->redirect('/user/login');
    }

    public function registerAction()
    {
        //Don't need to register them if they are already logged in.
        if (isset($_SESSION['user_id'])) {
            $this->app->redirect('/user');
        }

        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            //filter and validate all fields

            $firstName = trim($post['firstname']);
            if ($firstName == '') {
                $firstNameError = 'You must enter your first name to register with Lenox Hill Premedical.';
            } elseif ((strlen($firstName) < 2 || strlen($firstName) > 36)) {
                $firstNameError = 'Your first name must be between 2 and 36 characters.';
            } elseif (!preg_match('/^[\w\s]/', $firstName)) {
                $firstNameError = 'Your first name must be alphanumeric characters only.';
            }

            $lastName = trim($post['lastname']);
            if ($lastName == '') {
                $lastNameError = 'You must enter your last name to register with Lenox Hill Premedical.';
            } elseif ((strlen($lastName) < 2 || strlen($lastName) > 36)) {
                $lastNameError = 'Your last name must be between 2 and 36 characters.';
            } elseif (!preg_match('/^[\w\s]/', $lastName)) {
                $lastNameError = 'Your last name must be alphanumeric characters only.';
            }

            $email = trim($post['email']);
            if ($email == '') {
                $emailError = 'You must enter your email address to register with Lenox Hill Premedical.';
            } elseif (!(filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $emailError = 'The email address you typed in is not valid. Please try again.';
            } else {
                $db = $this->app->db;
                $stmt = $db->prepare('SELECT * FROM user WHERE email = :email');
                $stmt->execute(array(':email' => $email));
                if ($stmt->rowCount() !== 0) { //Someone already has an account with that email.
                    $emailError = 'The email address you typed in has already been registered with Lenox Hill Premedical.';
                    $emailError .= ' To log in with that email address, please click <a href="/user/login">here.</a>';
                };
            }

            $password = trim($post['password']);
            if ($password == '') {
                $passwordError = 'Your must enter a password to register with Lenox Hill Premedical.';
            } elseif ((strlen($password) < 6) || (strlen($password) > 50)) {
                $passwordError = 'Your password must be between 6 and 50 characters long.';
            }

            $passwordVerify = trim($post['passwordVerify']);
            if ($password !== $passwordVerify) {
                $passwordVerifyError = 'The passwords you typed in did not match. Please try again';
            }

            $errorArray = array('firstNameError', 'lastNameError', 'emailError', 'passwordError', 'passwordVerifyError');

            $formHasErrors = false;

            foreach ($errorArray as $e) {
                if (isset($$e)) {
                    $this->app->flash($e, $$e);
                    $formHasErrors = true;
                }
            }

            if ($formHasErrors) {
                $this->app->flash('firstName', $firstName);
                $this->app->flash('lastName', $lastName);
                $this->app->flash('email', $email);
                $this->app->flash('password', $password);
                $this->app->flash('passwordVerify', $passwordVerify);
                $this->app->redirect('/user/register');
            }

            //register the user.
            //add salt to password.
            $password = 'mcat'.$password.'2015';
            $stmt = $db->prepare('INSERT INTO user (email, password, first_name, last_name) VALUES (:email, :password, :firstName, :lastName)');
            $stmt->execute(array(':email' => $email, ':password' => md5($password), ':firstName' => $firstName, ':lastName' => $lastName));

            $userId = (int) $db->lastInsertId();

            //Deny them access to all full-lengths by setting their expiration dates to yesterday.
            $expDate = new \DateTime('yesterday', new \DateTimeZone('America/New_York'));
            for ($i=1; $i<6; $i++) {
                $this->grantFullLengthAccess($userId, $i, $expDate);
            }

            //Give them 1 year free access to full-length 1
            $oneYear = new \DateTime('now', new \DateTimeZone('America/New_York'));
            $oneYear = $oneYear->add(new \DateInterval('P365D'));
            $this->updateFullLengthExpirationDate($userId, 1, $oneYear);

            //Log them in.
            $user = $this->findById($userId);
            $_SESSION['user_id'] = $userId;
            $_SESSION['first_name'] = $user->first_name;
            $_SESSION['cfl'] = $user->current_full_length;

            //Redirect them to user homepage.
            $this->app->redirect('/user');
        }

        $this->app->render('user-register.phtml');
    }

    public function forgotPasswordAction()
    {
        //Clean expired forgot requests
        $oneDayAgo = new \DateTime('86400 seconds ago', new \DateTimeZone('America/New_York'));
        $sql = 'DELETE FROM user_password_reset WHERE request_time <= \''.$oneDayAgo->format('Y-m-d H:i:s').'\'';
        $this->app->db->query($sql);

        if ($this->app->request()->isPost()) {
            $email = $this->app->request->post('email');
            $email = filter_var($email, FILTER_VALIDATE_EMAIL); //will return false if it's not a valid email address
            echo "Mark1";
            if ($email) {
                echo "Mark2";
                $user = $this->findByEmail($email);
                echo "Mark3...";
                echo $email;
                if ($user) {
                    echo "Mark4";
                    $this->sendProcessForgotRequest((int) $user->user_id, $email, $user->first_name);
                }

                $this->app->view->setLayout('layout.phtml');
                $this->app->render('user-forgot-sent.phtml', array('email' => $email));
                return;
            } else {
                $this->app->flash('message', 'The email address is not valid');
                $this->app->redirect('/user/forgot-password');
            }
        }

        $this->app->render('user-forgot-password.phtml');
    }

    public function resetPasswordAction()
    {
        if (isset($_SESSION['user_id'])) {
            $this->app->redirect('/user');
        }

        //Clean expired forgot requests
        $oneDayAgo = new \DateTime('86400 seconds ago', new \DateTimeZone('America/New_York'));
        $sql = 'DELETE FROM user_password_reset WHERE request_time <= \''.$oneDayAgo->format('Y-m-d H:i:s').'\'';
        $this->app->db->query($sql);

        $token = $this->app->request->get('token');
        $sql = 'SELECT user_id FROM user_password_reset WHERE request_key = :token';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':token' => $token));
        $userId = $stmt->fetch(\PDO::FETCH_ASSOC)['user_id'];

        if ($userId === null) {
            $this->app->redirect('/user/forgot-password');
        }

        $user = $this->findById($userId);

        if ($this->app->request->isPost()) {
            //trim the passwords, make sure they are longer than six characters, and make sure they are the same
            $newCredential = trim($this->app->request->post('newCredential'));
            if (strlen($newCredential) < 6) {
                $this->app->flash('message', 'Your Lenox Hill Premedical password must be at least 6 characters long.');
                $this->app->redirect('/user/reset-password?token='.$token);
            }

            $newCredentialVerify = trim($this->app->request->post('newCredentialVerify'));
            if ($newCredential !== $newCredentialVerify) {
                $this->app->flash('message', 'The passwords you typed in did not match. Please try again.');
                $this->app->redirect('/user/reset-password?token='.$token);
            }

            //add salt to new credential
            $newCredential = 'mcat'.$newCredential.'2015';

            //Change password and then delete password request
            $sql = 'UPDATE user SET password = :newCredential WHERE user_id = :userId; ';
            $sql .= 'DELETE FROM user_password_reset WHERE request_key = :token';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':newCredential' => md5($newCredential), ':userId' => $user->user_id, ':token' => $token));

            $this->app->render('user-password-changed.phtml');
            return;
        }

        $this->app->render('user-reset-password.phtml', array('email' => $user->email, 'token' => $token));
    }

    public function provisionExamAction()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->app->redirect('/user/login');
        }

        $userId = $_SESSION['user_id'];
        $fullLengthNumber = filter_var($this->app->request->get('fullLengthNumber'), FILTER_VALIDATE_INT);

        //Phil - Jan, 2015 the in_array bit is for safety until full-lengths 4-5 are available.
        if (!$fullLengthNumber || in_array($fullLengthNumber, array(4,5))) {//suspect malicious activity
            unset($_SESSION);
            $this->app->redirect('/');
        }

        //check to see if they have access
        if (!$this->hasAccessToFullLength($userId, $fullLengthNumber)) {//suspect malicious activity
            unset($_SESSION);
            $this->app->redirect('/');
        }

        $numExams = $this->getNumExams($userId, $fullLengthNumber);
        if ($numExams < 10) {
            $examId = $this->createExam($userId, $fullLengthNumber);
            $this->populateExam($examId, $fullLengthNumber);
            if (isset($_GET['studyMode'])) {
                $this->processExamForStudyMode($examId, $fullLengthNumber);
            }
        } else {
            $this->app->flash(
                'message',
                'You have exceeded your exam limit. Please delete an old exam, and then you will be able to begin a new one. Thank you.'
            );
            $this->app->redirect('/user');
        }

        //update current_full_length status
        if ($_SESSION['cfl'] != $fullLengthNumber) {
            $this->updateCurrentFullLength($userId, $fullLengthNumber);
            $_SESSION['cfl'] = $fullLengthNumber;
        }

        $redirectString = '/full-length/';
        if (isset($_GET['studyMode'])) {
            //This will break if the phys directions page number changes. It's hard coded!
            $redirectString .= 'review-directions-page/'.$examId.'/'.$fullLengthNumber.'/14';
        } else {
            $redirectString .= 'cover-page/'.$examId.'/'.$fullLengthNumber.'/1';
        }
        $this->app->redirect($redirectString);
    }

    public function deleteExamAction()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->app->redirect('/user/login');
        }

        $userId = $_SESSION['user_id'];
        $examId = filter_var($this->app->request->get('examId'), FILTER_VALIDATE_INT);

        if (!$examId) {//suspect malicious activity
            unset($_SESSION);
            $this->app->redirect('/');
        }

        if (!$this->ownsExam($userId, $examId)) {//suspect malicious activity.
            unset($_SESSION);
            $this->app->redirect('/');
        }

        //Find out the full-length-number of the exam they are attempting to delete
        //so that you can update their current_full_length status.
        $sql = 'SELECT FullLengthNumber FROM exams WHERE ExamId = :examId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $examId));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $fullLengthNumber = $result['FullLengthNumber'];

        //Delete the exam
        $this->deleteExam($examId);

        //Update current_full_length status
        if ($_SESSION['cfl'] != $fullLengthNumber) {
            $this->updateCurrentFullLength($userId, $fullLengthNumber);
            $_SESSION['cfl'] = $fullLengthNumber;
        }

        $this->app->redirect('/user');
    }

    public function setApp(Slim $app)
    {
        $this->app = $app;
    }

    protected function hasAccessToFullLength($userId, $fullLengthNumber)
    {
        $sql = 'SELECT ExpDate FROM full_length_access WHERE UserId = :userId AND FullLengthNumber = :fullLengthNumber';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':userId' => $userId, ':fullLengthNumber' => $fullLengthNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (empty($result)) {
            return false;
        }

        $expDate = $result['ExpDate'];
        $now = new \DateTime();
        if ($expDate > $now->format('Y-m-d H:i:s')) {
            return true;
        } else {
            return false;
        }
    }

    protected function getNumExams($userId, $fullLengthNumber)
    {
        $sql = 'SELECT * FROM exams WHERE UserId = :userId AND FullLengthNumber = :fullLengthNumber';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':userId' => $userId, ':fullLengthNumber' => $fullLengthNumber));
        return $stmt->rowCount();
    }

    protected function createExam($userId, $fullLengthNumber)
    {
        $dateTime = new \DateTime('now', new \DateTimeZone('America/New_York'));
        $dateCreated = $dateTime->format('Y-m-d H:i:s');
        $sql = 'INSERT INTO exams (UserId, FullLengthNumber, CurrentPageNumber, DateCreated)';
        $sql .= ' VALUES (:userId, :fullLengthNumber, 1, :dateCreated)';
        $db = $this->app->db;
        $stmt = $db->prepare($sql);
        $stmt->execute(array(':userId' => $userId, ':fullLengthNumber' => $fullLengthNumber, ':dateCreated' => $dateCreated));
        return $db->lastInsertId();
    }

    protected function ownsExam($userId, $examId)
    {
        $sql = 'SELECT * FROM exams WHERE (UserId, ExamId) = (:userId, :examId)';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':userId' => $userId, ':examId' => $examId));
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return (count($result) == 1) ? true : false;
    }

    protected function deleteExam($examId)
    {
        $sql = 'DELETE FROM exams WHERE ExamId = :examId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $examId));//Should cascade and delete all submitted answers automatically.
    }

    protected function populateExam($examId, $fullLengthNumber)
    {
        //Create the scan tron.
        $numQuestions = McatSection::PHYS_NUMBER_ITEMS+McatSection::CRIT_NUMBER_ITEMS+McatSection::BIO_NUMBER_ITEMS+McatSection::PSY_NUMBER_ITEMS;
        $string = '';
        for ($i=1; $i<$numQuestions; $i++) {
            $string .= ' (:examId, '.$i.', NULL, 0),';
        }
        $string .= ' (:examId, '.$i.', NULL, 0)';
        $sql = 'INSERT INTO submitted_answers VALUES'.$string;
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $examId));

        //Create the annotation material
        //Get all page numbers for content pages of exam
        $sql = 'SELECT DISTINCT PageNumber FROM full_length_info';
        $sql .= ' WHERE FullLengthNumber = :fullLengthNumber AND PageType = \'content\'';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $fullLengthNumber));
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        //Create the annotation scan tron.
        $string = '';
        foreach ($result as $key => $value) {
            $string .= '(:examId, '.$value['PageNumber'].'),';
        }
        $string = rtrim($string, ',');
        $sql = 'INSERT INTO annotations (ExamId, PageNumber) VALUES '.$string;
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $examId));
    }

    protected function purchaseExam()
    {
        $post = $this->app->request->post();
        $token = $post['stripeToken'];
        $email = $post['stripeEmail'];
        $fullLengthNumber = $post['fullLengthNumber'];
        $promoCode = $post['promoCode'];

        //Make sure nobody is buying an exam using the anonymous student account
        if ($_SESSION['user_id'] == McatSection::ANONYMOUS_STUDENT_ID) {
            $this->app->flash('message', 'You cannot puchase additional tests using this account. Lenox Hill Premedical did not process this transaction. To purchase an additional full-length MCAT, please sign out of this account and sign in with your own personal account. Thank you!<br/><br/>');
            $this->app->redirect('/user');
        }
        //verfiy that the email they typed into the Stripe form is their actual user email. Will make it
        //easier to look at charges on Stripe's console.
        $user = $this->findById($_SESSION['user_id']);
        if ($email !== $user->email) {//the email they typed into the Stripe form is not the same as their account email
            $this->app->flash(
                'message',
                'The email you entered to purchase this exam does not match the email associated with this account.
                For security purposes, Lenox Hill Premedical did not process this transaction. Please make sure the email
                address you type into the purchase form is the same as the email address associated with this Lenox Hill Premedical account.<br/><br/>'
            );
            $this->app->redirect('/user');
        }

        $amount = 5000;

        //If they are using a promo code, get the new price.
        if ($promoCode != '') {
            $sql = 'SELECT user_id, email, first_name, last_name, promo_code, promo_code_discount FROM user WHERE promo_code = :promoCode';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':promoCode' => $promoCode));
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (empty($result)) {//something is severely wrong (hacked?). Someone is logged in, and has POSTED a bogus promo code.
                $this->app->flash('message', 'Something went wrong implementing the promo code. Lenox Hill Premedical did not process this transaction. Please try or again or contact a Lenox Hill Premedical MCAT instructor.');
                $this->app->redirect('/user');
            } else {
                $amount = 5000 * (100 - $result['promo_code_discount'])/100;
                $this->sendEmailConfirmingPromoCodeUsage($result);
            }
        }

        //charge their credit card.
        require_once(dirname(__DIR__).'/stripe-php-3.4.0/stripe-php-3.4.0/init.php'); //careful. This will break if directory structure changes
        \Stripe\Stripe::setApiKey($this->app->config('stripe_secret_key'));

        try {
            \Stripe\Charge::create(array(
              'amount'   => $amount,
              'currency' => 'usd',
              'source'   => $token,
              'description' => 'Full-Length MCAT '.$fullLengthNumber,
              'receipt_email' => $email
            ));
        } catch (\Exception $e) { // need this, otherwise using test cards will break app
        }

        //give them access to their full-length
        $userId = $_SESSION['user_id'];
        $fullLengthNumber = $post['fullLengthNumber'];
        $currentDate = new \DateTime('now', new \DateTimeZone('America/New_York'));
        $expirationDate = $currentDate->add(new \DateInterval('P91D'));
        $this->updateFullLengthExpirationDate($userId, $fullLengthNumber, $expirationDate);

        //update the current_full_length field in user table;
        $this->updateCurrentFullLength($userId, $fullLengthNumber);
        $_SESSION['cfl'] = $fullLengthNumber;

        $this->app->flash('message', 'Payment Received. Thank you. Good luck on your MCAT!');
        $this->app->redirect('/user');
    }

    protected function updateFullLengthExpirationDate($userId, $fullLengthNumber, \DateTime $expDate)
    {
        $sql = 'UPDATE full_length_access SET ExpDate = :expDate';
        $sql .= ' WHERE UserId = :userId AND FullLengthNumber = :fullLengthNumber';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(
            array(':expDate' => $expDate->format('Y-m-d H:i:s'), ':userId' => $userId, ':fullLengthNumber' => $fullLengthNumber)
        );
    }

    protected function updateCurrentFullLength($userId, $fullLengthNumber)
    {
        $sql = 'UPDATE user SET current_full_length = :currentFullLength WHERE user_id = :userId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':currentFullLength' => $fullLengthNumber, ':userId' => $userId));
    }

    protected function getExpDates($userId)
    {
        $sql = 'SELECT FullLengthNumber, ExpDate FROM full_length_access WHERE UserId = :userId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':userId' => $userId));
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $timeZone = new \DateTimeZone('America/New_York');
        $array = array();
        foreach ($result as $key => $value) {
            $array[$value['FullLengthNumber']] = \DateTime::createFromFormat('Y-m-d H:i:s', $value['ExpDate'], $timeZone);
        }
        return $array;
    }

    protected function getAllExams($userId)
    {
        $sql = 'SELECT DISTINCT exams.FullLengthNumber, ExamId, CurrentPageNumber, TimeRemaining, Status';
        $sql .= ', PageType, DATE_FORMAT(DateCreated, \'%b %d %Y at %h:%i %p\') as DateCreated, BioScore, PhysScore, PsyScore, CritScore';
        $sql .= ' FROM exams JOIN full_length_info ON (exams.CurrentPageNumber = full_length_info.PageNumber AND exams.FullLengthNumber = full_length_info.FullLengthNumber)';
        $sql .= ' WHERE UserId = :userId';
        $sql .= ' ORDER BY exams.FullLengthNumber, exams.ExamId DESC';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':userId' => $userId));
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function findByEmail($email)
    {
        $sql = 'SELECT * FROM user WHERE email = :email';
        echo $sql;
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':email' => $email));
        echo $stmt->fetch(\PDO::FETCH_OBJ);
        return $stmt->fetch(\PDO::FETCH_OBJ);
    }

    protected function findById($userId)
    {
        $sql = 'SELECT * FROM user WHERE user_id = :userId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':userId' => $userId));
        return $stmt->fetch(\PDO::FETCH_OBJ);
    }

    protected function sendProcessForgotRequest($userId, $email, $firstName)
    {
        echo "I am in sendProcessForgotRequest Yoda Soda";
        $sql = 'DELETE FROM user_password_reset WHERE user_id = :userId'; //Delete any old reset requests for that user.
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':userId' => $userId));

        $now = new \DateTime('now', new \DateTimeZone('America/New_York'));
        $requestKey = strtoupper(substr(sha1($userId . '####' . $now->getTimestamp()), 0, 15));
        $sql = 'INSERT INTO user_password_reset VALUES (:requestKey, :userId, :requestTime)';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':requestKey' => $requestKey, ':userId' => $userId, ':requestTime' => $now->format('Y-m-d H:i:s')));



//         $textBody = 'Dear '.ucfirst($firstName).',
//
// You have received this email, because you initiated a password reset with Lenox Hill Premedical.';
//         $textBody .= ' To Continue with the process, please click on the link https://lenoxhillpremedical.com/user/reset-password?token='.$requestKey;
//         $textBody .= '
//
// If you did not initiate this password reset, please contact your Lenox Hill Premedical Instructor or reply to this email and let us know.
//
// Happy Studying!
//
// -Lenox Hill Premedical';

        $textBody = 'foo';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->Username = getenv('EMAIL_USERNAME');
            $mail->Password = getenv('EMAIL_PASSWORD');
            $mail->Subject = "Lenox Hill Premedical Password Reset";
            $mail->Body = $textBody;

            $mail->setFrom(getenv('EMAIL_USERNAME'));
            $mail->addAddress($email, ucfirst($firstName));
            $mail->send();
            echo "Email message sent!";
        } catch (Exception $e) {
            echo "Error in sending email. Mailer error: {$mail->ErrorInfo}";
        }
    }

    protected function grantFullLengthAccess($userId, $fullLengthNumber, \DateTime $expDate)
    {
        $db = $this->app->db;
        $sql = 'INSERT INTO full_length_access (UserId, FullLengthNumber, ExpDate)';
        $sql .= ' VALUES (:userId, :fullLengthNumber, :expDate)';
        $stmt = $db->prepare($sql);
        $stmt->execute(array(':userId' => $userId, ':fullLengthNumber' => $fullLengthNumber, ':expDate' => $expDate->format('Y-m-d H:i:s')));
    }

    //This function is not executed anywhere in the code base. It's for future single-use cases so as to not reinvent the wheel each time.
    protected function shuffleAnswerChoices($fullLengthNumber)
    {
        for ($i = 1; $i < 231; $i++) {
            $random = array('A', 'B', 'C', 'D');
            shuffle($random);
            $sql = 'UPDATE full_length_info SET Answer = :answer, Distractor1 = :distractor1, Distractor2 = :distractor2, Distractor3 = :distractor3 ';
            $sql .= 'WHERE FullLengthNumber = '.$fullLengthNumber.' AND PageType = \'content\' AND ItemNumber = '.$i;
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':answer' => $random[0], ':distractor1' => $random[1], ':distractor2' => $random[2], ':distractor3' => $random[3]));
        }
    }

    protected function processExamForStudyMode($examId, $fullLengthNumber)
    {
        //update the exam: set the scores, the time remaining, and the current page to the first phys directions page.
        $sql = 'UPDATE exams SET Status = \'scored\', TimeRemaining = NULL, CurrentPageNumber = ';
        $sql .= '(SELECT PageNumber FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageType = \'directions\' AND Section = \'phys\')';
        $sql .= ' WHERE ExamId = :examId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $examId, ':fullLengthNumber' => $fullLengthNumber));
    }

    protected function applyPromoCode($promoCode)
    {
        $sql = 'SELECT user_id, email, first_name, last_name, promo_code, promo_code_discount FROM user WHERE promo_code = :promoCode';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':promoCode' => $promoCode));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (empty($result)) {
            return false;
        } else {
            return $result;
        }
    }

    protected function sendEmailConfirmingPromoCodeUsage(array $promoCodeInfo)
    {
        $emailOfPromoCodeOwner = $promoCodeInfo['email'];
        $firstName = $promoCodeInfo['first_name'];
        $lastName = $promoCodeInfo['last_name'];
        $promoCode = $promoCodeInfo['promo_code'];

        $textBody = 'Dear '.ucfirst($firstName).',<br/><br/>';
        $textBody .= 'Congratulations! Someone has purchased a Lenox Hill Premedical product using your promo code';
        $textBody .= ' <strong>('.$promoCode.')</strong>. If the payment clears, you should expect to receive a check at the end of this pay cycle. You do not have to take any action. If you have any specific questions, please reply to this email. Thank you for the referral!<br/><br/>';
        $textBody .= 'Happy Studying!<br/><br/>';
        $textBody .= '- Lenox Hill Premedical';
        try {
            $message = new \google\appengine\api\mail\Message();
            $message->setSubject('Your Lenox Hill Premedical promo code was used!');
            $message->setSender('lenoxhillpremedical@gmail.com');
            $message->addTo($emailOfPromoCodeOwner);
            $message->setHtmlBody($textBody);
            $message->send();
        } catch (\Exception $e) {
            return false;
        }
    }
}
