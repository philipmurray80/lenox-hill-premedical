<?php
namespace Controller;

use Slim\Slim;

class IndexController
{
    public $app;

    protected $actions = array(
        'index'               => 'indexAction',
        //'mcat-tutoring'		  => 'mcatTutoringAction',
        'mcat-classes'		  => 'mcatClassesAction',
        'sponsorship'	      => 'sponsorshipAction',
        'class-registration'  => 'classRegistrationAction',
        //'simulated-mcats'   => 'simulatedMcatsAction',
        //'study-halls'		  => 'studyHallsAction',
        'practice-tests'        => 'practiceTestsAction',
        'contact-us' 			  => 'contactUsAction',
        //'our-story'           => 'ourStoryAction',
        //'employment'          => 'employmentAction',
        'tutoring-invoice'	  => 'tutoringInvoiceAction',
        //'internships'         => 'internshipsAction',
    );

    public function dispatchAction($action)
    {
        $method = $this->actions[$action];
        return $this->$method();
    }

    public function indexAction()
    {
        $this->app->render('index.phtml');
    }

    /*public function mcatTutoringAction() {
        $this->app->render('mcat-tutoring.phtml');
    }*/

    /*public function simulatedMcatsAction() {
        $this->app->render('simulated-mcats.phtml');
    }*/

    /*public function studyHallsAction() {
        $this->app->render('study-halls.phtml');
    }*/

    public function practiceTestsAction()
    {
        $this->app->render('practice-tests.phtml');
    }

    public function sponsorshipAction()
    {
        $this->app->render('sponsorship.phtml');
    }

    public function contactUsAction()
    {
        $this->app->render('contact-us.phtml');
    }

    public function mcatClassesAction()
    {
        $sql = 'SELECT classes.*, Seats - COUNT(*) as \'AvailableSeats\' FROM classes LEFT OUTER JOIN class_registrations ON classes.ClassId = class_registrations.ClassId WHERE classes.Start > NOW() GROUP BY classes.ClassId ORDER BY classes.Start ASC';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute();
        $classes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($classes as $key => $value) {
            $classes[$key]['Start'] = \DateTime::createFromFormat('Y-m-d H:i:s', $value['Start'], new \DateTimeZone('America/New_York'));
            $classes[$key]['End'] = \DateTime::createFromFormat('Y-m-d H:i:s', $value['End'], new \DateTimeZone('America/New_York'));
        }

        $this->app->render(
            'mcat-classes.phtml',
            array(
                'classes' => $classes,
            )
        );
    }

    public function classRegistrationAction()
    {
        //Need this array for get and post so do it first.
        $prettyNames = array(
            'information' => 'MCAT Information Session',
            'practice'	  => 'MCAT Drill Session',
            'simulatedtest' => 'Simulated Test',
            'physics1' => 'MCAT Physics 1',
            'physics2' => 'MCAT Physics 2',
            'genchem1' => 'MCAT General Chemistry 1',
            'genchem2' => 'MCAT General Chemistry 2',
            'genchem3' => 'MCAT General Chemistry 3',
            'organicchem' => 'MCAT Organic Chemistry',
            'biochem1' => 'MCAT Biochemistry 1',
            'biochem2' => 'MCAT Biochemistry 2',
            'biology1' => 'MCAT Biology 1',
            'biology2' => 'MCAT Biology 2',
            'biology3' => 'MCAT Biology 3',
            'cars' => 'MCAT Critical Analysis and Reasoning Skills',
            'psychology1' => 'MCAT Psychology 1',
            'psychology2' => 'MCAT Psychology 2',
            'sociology' => 'MCAT Sociology'
        );

        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $classIdFromPost = filter_var($post['classId'], FILTER_VALIDATE_INT);
            $classIdFromGet = filter_var($this->app->request->get('classId'), FILTER_VALIDATE_INT);
            if (!$classIdFromGet || !$classIdFromPost || ($classIdFromPost != $classIdFromGet)) {//someone hacking.
                $this->app->flash('message', 'Something went wrong with your class registration process. If you were attempting to purchase tickets, your credit card was not charged');
                $this->app->redirect('/mcat-classes');
            } else {
                $classId = $classIdFromPost;
            }


            //filter and validate all fields
            $firstName = trim($post['firstname']);
            if ($firstName == '') {
                $firstNameError = 'You must enter your first name to reserve seats for this class.';
                $firstNameError .= (isset($post['stripeToken'])) ? ' Your credit card was not charged.' : '';
            } elseif ((strlen($firstName) < 2 || strlen($firstName) > 36)) {
                $firstNameError = 'Your first name must be between 2 and 36 characters.';
                $firstNameError .= (isset($post['stripeToken'])) ? ' Your credit card was not charged.' : '';
            } elseif (!preg_match('/^[\w\s]/', $firstName)) {
                $firstNameError = 'Your first name must be alphanumeric characters only.';
                $firstNameError .= (isset($post['stripeToken'])) ? ' Your credit card was not charged.' : '';
            }

            $lastName = trim($post['lastname']);
            if ($lastName == '') {
                $lastNameError = 'You must enter your last name to reserve a seat for this class.';
                $lastNameError .= (isset($post['stripeToken'])) ? ' Your credit card was not charged.' : '';
            } elseif ((strlen($lastName) < 2 || strlen($lastName) > 36)) {
                $lastNameError = 'Your last name must be between 2 and 36 characters.';
                $lastNameError .= (isset($post['stripeToken'])) ? ' Your credit card was not charged.' : '';
            } elseif (!preg_match('/^[\w\s]/', $lastName)) {
                $lastNameError = 'Your last name must be alphanumeric characters only.';
                $lastNameError .= (isset($post['stripeToken'])) ? ' Your credit card was not charged.' : '';
            }


            $email = (isset($post['stripeToken'])) ? $post['stripeEmail'] : trim($post['email']);
            if ($email == '') {
                $emailError = 'You must enter your email address to reserve a seat for this class.';
            } elseif (!(filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $emailError = 'The email address you typed in is not valid.';
                $emailError .= (isset($post['stripeToken'])) ? ' Your credit card was not charged.' : '';
                $emailError .= ' Please try again.';
            } elseif ($email == 'student@lenoxhillpremedical.com') { //todo - get rid of hard code.
                $emailError = 'The email address you entered cannot be used to register for a class.';
                $emailError .= (isset($post['stripeToken'])) ? ' Your credit card was not charged.' : '';
                $emailError .= ' Please enter the email address of the student taking the class.';
            } else {
                $db = $this->app->db;
                $stmt = $db->prepare('SELECT * FROM class_registrations WHERE ClassId = :classId AND email = :email');
                $stmt->execute(array(':classId' => $classId, ':email' => $email));
                if ($stmt->rowCount() !== 0) { //Someone with that email already has a seat in that class.
                    $emailError = 'A person with the email address you typed in has already reserved a seat in this class.';
                    $emailError .= (isset($post['stripeToken'])) ? ' Your credit card was not charged.' : '';
                };
            }

            $errorArray = array('firstNameError', 'lastNameError', 'emailError');
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
                $this->app->redirect('/class-registration?classId='.$classId);
            }

            //get the info about the class
            $sql = 'SELECT classes.*, Seats - COUNT(*) as \'AvailableSeats\' FROM classes LEFT OUTER JOIN class_registrations ON classes.ClassId = class_registrations.ClassId WHERE classes.ClassId = :classId';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':classId' => $classId));
            $class = $stmt->fetch(\PDO::FETCH_ASSOC);

            //Make sure there are seats available.
            if ($class['AvailableSeats'] < 1) {//No seats left.
                $message = 'There are no more seats available for this class.';
                $message .= (isset($post['stripeToken'])) ? ' Your credit card was not charged.' : '';
                $message .= ' Click <a href="mcat-classes">here</a> to search for another class.';
                $this->app->flash('message', '<span class="text-danger">There are no more seats available for this class. If you were purchasing tickets, your credit card was not charged. Click <a href="mcat-classes" class="text-danger"><strong>here</strong></a> to search for another class.</span>');
                $this->app->redirect('class-registration?classId='.$classId);
            }

            //register the student for the class
            $sql = 'INSERT INTO class_registrations (ClassId, FirstName, LastName, Email) VALUES (:classId, :firstName, :lastName, :email)';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':classId' => $classId, ':firstName' => $firstName, ':lastName' => $lastName, ':email' => $email));
            $registrantId = $db->lastInsertId();



            if ($class) {
                foreach ($prettyNames as $key => $value) {
                    if ($class['Topic'] == $key) {
                        $class['Topic'] = $value;
                    }
                }
                $class['Start'] = \DateTime::createFromFormat('Y-m-d H:i:s', $class['Start'], new \DateTimeZone('America/New_York'));
                $class['End'] = \DateTime::createFromFormat('Y-m-d H:i:s', $class['End'], new \DateTimeZone('America/New_York'));
            } else {//The query returned false (class does not exist). This should never happen (suspect something fishy.)
                $this->app->flash('message', 'There was a problem registering for your class. The class ID was not recognized.');
                $this->app->redirect('/mcat-classes'); //cannot redirect them back to class registration page because don't know class ID!
            }

            if (isset($post['stripeToken'])) {//class is not free
                $chargeAmount = $class['Price'] * 100; //amount to be charged in cents.
                //charge the credit card.
                require_once(dirname(__DIR__).'/stripe-php-3.4.0/stripe-php-3.4.0/init.php');
                \Stripe\Stripe::setApiKey($this->app->config('stripe_secret_key'));

                try {
                    \Stripe\Charge::create(array(
                    'amount' => $chargeAmount,
                    'currency' => 'usd',
                    'source' => $post['stripeToken'],
                    'description' => $class['Topic'],
                    'receipt_email' => $post['stripeEmail']
                    ));
                } catch (\Stripe\Error\Card $e) {
                    // Since it's a decline, \Stripe\Error\Card will be caught
                    $body = $e->getJsonBody();
                    $err  = $body['error'];
                    //Redirect to invoice page
                    $this->app->flash('message', 'Your credit card was approved, but the charge was declined. Your card was <i>not</i> charged.
					Lenox Hill Premedical\'s payment processor issued the following message: \"<i>'.$err['message'].'</i>\". Please try a different card
					or contact Lenox Hill Premedical about how to proceed. Thank you.');
                    $this->app->redirect('/class-registration?classId='.$classId);
                } catch (\Stripe\Error\RateLimit $e) {
                    $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
					contact Lenox Hill Premedical for more information.');
                    $this->app->redirect('/class-registration?classId='.$classId);
                } catch (\Stripe\Error\InvalidRequest $e) {
                    // Invalid parameters were supplied to Stripe's API
                    $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
					contact Lenox Hill Premedical for more information (If a test card was used, please ignore this message.)');
                    //$this->app->redirect('/class-registration?classId='.$classId); commented out this line, because test cards break stripe's app.
                } catch (\Stripe\Error\Authentication $e) {
                    // Authentication with Stripe's API failed
                    // (maybe you changed API keys recently)
                    $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
					contact Lenox Hill Premedical for more information.');
                    $this->app->redirect('/class-registration?classId='.$classId);
                } catch (\Stripe\Error\ApiConnection $e) {
                    // Network communication with Stripe failed
                    $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
					contact Lenox Hill Premedical for more information.');
                    $this->app->redirect('/class-registration?classId='.$classId);
                } catch (\Stripe\Error\Base $e) {
                    // Display a very generic error to the user, and maybe send
                    // yourself an email
                    $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
					contact Lenox Hill Premedical.');
                    $this->app->redirect('/class-registration?classId='.$classId);
                } catch (Exception $e) {
                    // Something else happened, completely unrelated to Stripe
                    $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
					contact Lenox Hill Premedical for more information.');
                    $this->app->redirect('/class-registration?classId='.$classId);
                }
            }

            //send confirmation emails.
            $this->sendClassRegistrationConfirmationEmail($firstName, $lastName, $email, $class, $registrantId);

            $message = '<h4 class="text-success">Registration successful! You will receive an email shortly confirming your MCAT class registration. Your confirmation number is '.$registrantId.'. You do not need to take any further action. Click <a href="/"><strong class="text-success">here</strong></a> to return to the homepage. <br/><br/>Happy Studying!<br/><br/></h2>';
            $this->app->flash('message', $message);
            $this->app->redirect('/class-registration?classId='.$classId);
        }

        //If S_GET['classId'] is not set, something is fishy.
        $classId = filter_var($this->app->request->get('classId'), FILTER_VALIDATE_INT);
        if (!$classId) {
            $this->app->redirect('/mcat-classes');
        }

        //if they're already logged in, get their info and pre-populate forms
        $user = false;
        if (isset($_SESSION['user_id'])) {
            $sql = 'SELECT first_name, last_name, email FROM user WHERE user_id = :userId';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':userId' => $_SESSION['user_id']));
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        //Get the class info
        $sql = 'SELECT * FROM classes WHERE ClassId = :classId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':classId' => $classId));
        $class = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($class) {
            $class['Start'] = \DateTime::createFromFormat('Y-m-d H:i:s', $class['Start'], new \DateTimeZone('America/New_York'));
            $class['End'] = \DateTime::createFromFormat('Y-m-d H:i:s', $class['End'], new \DateTimeZone('America/New_York'));
        } else {//The query returned false (class does not exist).
            $this->app->flash('message', 'There was a problem registering for your class. The class ID was not recognized.');
            $this->app->redirect('/mcat-classes');
        }

        foreach ($prettyNames as $key => $value) {
            if ($class['Topic'] == $key) {
                $class['Topic'] = $value;
            }
        }

        //In case they are reserving tickets that are not free.
        $stripePublishableKey = $this->app->config('stripe_publishable_key');

        $this->app->render('class-registration.phtml', array(
            'user' => $user,
            'class' => $class,
            'stripePublishableKey' => $stripePublishableKey,
        ));
    }

    /*public function ourStoryAction() {
        $this->app->render('our-story.phtml');
    }*/

    /*public function employmentAction() {
        $this->app->render('employment.phtml');
    }*/

    public function tutoringInvoiceAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $invoiceKey = $this->app->request->get('token');
            //Charge the credit card.
            //Get the amount to be charged
            $sql = 'SELECT BillableHours, HourlyRate FROM tutoring_invoices WHERE InvoiceUrlKey = :invoiceUrlKey';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':invoiceUrlKey' => $invoiceKey));
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $billableHours = $result['BillableHours'];
            $hourlyRate = $result['HourlyRate'];
            $chargeAmount = intval($billableHours*$hourlyRate*100);//amount to be charged in cents.

            require_once(dirname(__DIR__).'/stripe-php-3.4.0/stripe-php-3.4.0/init.php');
            \Stripe\Stripe::setApiKey($this->app->config('stripe_secret_key'));

            try {
                \Stripe\Charge::create(array(
                'amount' => $chargeAmount,
                'currency' => 'usd',
                'source' => $post['stripeToken'],
                'description' => 'MCAT Tutoring',
                'receipt_email' => $post['stripeEmail']
                ));

                //Set the invoice Status to paid
                $sql = 'UPDATE tutoring_invoices SET Status = \'paid\'';
                $sql .= ' WHERE InvoiceUrlKey = :invoiceUrlKey';
                $stmt = $this->app->db->prepare($sql);
                $stmt->execute(array(':invoiceUrlKey' => $invoiceKey));

                if ($stmt->rowCount() != 1) {//didn't update anything
                    $this->app->flash('message', 'An error occurred, possibly because the invoice
					was already paid. Your credit card was not charged. Please contact your Lenox
					Hill Premedical MCAT tutor to find out more information.');
                    $this->app->redirect('/tutoring-invoice?token='.$invoiceKey);
                }
            } catch (\Stripe\Error\Card $e) {
                // Since it's a decline, \Stripe\Error\Card will be caught
                $body = $e->getJsonBody();
                $err  = $body['error'];
                //Redirect to invoice page
                $this->app->flash('message', 'Your credit card was approved, but the charge was declined. Your card was <i>not</i> charged.
				Lenox Hill Premedical\'s payment processor issued the following message: \"<i>'.$err['message'].'</i>\". Please try a different card
				or contact your MCAT<sup>&reg;</sup> instructor about how to proceed. Thank you.');
                $this->app->redirect('/tutoring-invoice?token='.$invoiceKey);
            } catch (\Stripe\Error\RateLimit $e) {
                $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
				contact your MCAT instructor for more information.');
                $this->app->redirect('/tutoring-invoice?token='.$invoiceKey);
            } catch (\Stripe\Error\InvalidRequest $e) {
                // Invalid parameters were supplied to Stripe's API
                $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
				contact your MCAT instructor for more information.');
                $this->app->redirect('/tutoring-invoice?token='.$invoiceKey);
            } catch (\Stripe\Error\Authentication $e) {
                // Authentication with Stripe's API failed
                // (maybe you changed API keys recently)
                $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
				contact your MCAT instructor for more information.');
                $this->app->redirect('/tutoring-invoice?token='.$invoiceKey);
            } catch (\Stripe\Error\ApiConnection $e) {
                // Network communication with Stripe failed
                $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
				contact your MCAT instructor for more information.');
                $this->app->redirect('/tutoring-invoice?token='.$invoiceKey);
            } catch (\Stripe\Error\Base $e) {
                // Display a very generic error to the user, and maybe send
                // yourself an email
                $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
				contact your MCAT instructor for more information.');
                $this->app->redirect('/tutoring-invoice?token='.$invoiceKey);
            } catch (Exception $e) {
                // Something else happened, completely unarelated to Stripe
                $this->app->flash('message', 'Your credit card was approved, but something went wrong. Your card was <i>not</i> charged. Please
				contact your MCAT instructor for more information.');
                $this->app->redirect('/tutoring-invoice?token='.$invoiceKey);
            }

            //Redirect to invoice page
            $this->app->flash('message', 'Your payment has been processed successfully. Thank you!');
            $this->app->redirect('/tutoring-invoice?token='.$invoiceKey);
        }

        $invoiceKey = $this->app->request->get('token');
        $sql = 'SELECT * FROM tutoring_invoices JOIN user ON tutoring_invoices.Tutor = user.user_id';
        $sql .= ' WHERE InvoiceUrlKey = :token';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':token' => $invoiceKey));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            $this->app->redirect('/');
        } //invoice not there
        $stripePublishableKey = getenv('STRIPE_PUBLISHABLE_LIVE_KEY'); // Live mode for tutoring invoices.
        // $stripePublishableKey = $this->app->config('stripe_publishable_key');

        $startDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $result['StartTime'], new \DateTimeZone('America/New_York'));
        $endDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $result['EndTime'], new \DateTimeZone('America/New_York'));

        $tutor = ucfirst($result['last_name']).', '.ucfirst($result['first_name']);
        $balanceInCents = $result['BillableHours']*$result['HourlyRate']*100;
        $balanceInDollars = number_format(floatval($balanceInCents/100), 2);


        $this->app->render(
            'tutoring-invoice.phtml',
            array(
                'stripePublishableKey' => $stripePublishableKey,
                'invoiceKey'           => $invoiceKey,
                'status'               => $result['Status'],
                'tutor'                => $tutor,
                'tutoree'			   => $result['Tutoree'],
                'date'                 => $startDateTime->format('F jS, Y'),
                'startTime'            => $startDateTime->format('g:ia'),
                'endTime'              => $endDateTime->format('g:ia'),
                'billableHours'        => $result['BillableHours'],
                'hourlyRate'		   => $result['HourlyRate'],
                'balanceInCents'       => $balanceInCents,
                'balanceInDollars'     => $balanceInDollars,
            )
        );
    }

    /*public function internshipsAction() {
        $this->app->render('internships.phtml');
    }*/

    public function setApp(Slim $app)
    {
        $this->app = $app;
    }

    //We will eventually task queue this function,
    protected function sendClassRegistrationConfirmationEmail($firstName, $lastName, $email, array $class, $registrantId)
    {
        $textBody = 'Dear '.ucfirst($firstName).',<br/><br/>';
        $textBody .= 'Thank you for registering for a Lenox Hill Premedical MCAT<sup>&reg;</sup> class. You are confirmed for the class shown below. If you purchased tickets for this class, you will receive a separate email receipt.<br/><br/>See you in class and happy studying!<br/><br/><br/>';

        $textBody .= 'Your Information:';
        $textBody .= '<table><tbody>';
        $textBody .= '<tr><td><b>Name</b></td><td>'.ucfirst($firstName).' '.ucfirst($lastName).'</td></tr>';
        $textBody .= '<tr><td><b>Email</b></td><td>'.$email.'</td></tr>';
        $textBody .= '<tr><td><b>Confirmation<br/>Number</b></td><td>'.$registrantId.'</td></tr></tbody></table><br/><br/>';

        $textBody .= 'Class Information:';
        $textBody .= '<table><tbody>';
        $textBody .= '<tr><td><b>Topic</b></td><td>'.$class['Topic'].'</td></tr>';
        $textBody .= '<tr><td><b>Location</b></td><td>'.$class['Location'].'</td></tr>';
        $textBody .= '<tr><td><b>Date</b></td><td>'.$class['Start']->format('F jS Y').'</td></tr>';
        $textBody .= '<tr><td><b>Start Time</b></td><td>'.$class['Start']->format('g:ia').'</td></tr>';
        $textBody .= '<tr><td><b>End Time</b></td><td>'.$class['End']->format('g:ia').'</td></tr>';
        $textBody .= '<tr><td><b>Instructor</b></td><td>'.$class['Instructor'].'</td></tr></tbody></table><br/><br/>';
        $textBody .= 'If you have questions or concerns about this class, please let us know by replying to this email.';
        try {
            $message = new \google\appengine\api\mail\Message();
            $message->setSubject('MCAT Class Confirmation');
            $message->setSender('lenoxhillpremedical@gmail.com');
            $message->addTo($email);
            $message->addBcc('lenoxhillpremedical@gmail.com');
            $message->setHtmlBody($textBody);
            $message->send();
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
}
