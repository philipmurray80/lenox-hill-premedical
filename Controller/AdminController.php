<?php
namespace Controller;

use Slim\Slim;


class AdminController {

    public $app;

    protected $actions = array(
        'index'                   => 'manageUsersAction',
        'manage-users'            => 'manageUsersAction',
        'delete-user'             => 'deleteUserAction',
        'update-user'             => 'updateUserAction',
        'manage-admins'           => 'manageAdminsAction',
        'create-tutoring-invoice' => 'createTutoringInvoiceAction',
    );

    public function dispatchAction($action) {
        $method = $this->actions[$action];
        return $this->$method();
    }

    public function indexAction() {
		//Keep this around in case you want a landing page in the future.
        $this->app->render('admin-index.phtml');
    }

    public function manageUsersAction() {
        $page = ($this->app->request->get('page')) ?: 1;
        $page = filter_var($page,FILTER_VALIDATE_INT);

        //Get the count of the users to be queried
        $sql = 'SELECT COUNT(*) FROM user JOIN full_length_access ON user.user_id = full_length_access.UserId ORDER BY last_name, first_name';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute();

        $count = $stmt->fetch()[0]; //The result will be five times as large as the actual user count
        $limit = 10 * 5;
        $lastPage = (int) ceil($count/$limit);
        if ($page < 1) {$page = 1;} elseif ($page > $lastPage) {$page = $lastPage;} //Restrict page numbers.
        $offset = ($page - 1) * $limit;

        //Get all the users from the requested query
        $sql = 'SELECT * FROM user JOIN full_length_access ON user.user_id = full_length_access.UserId';
        $sql .= ' ORDER BY last_name, first_name LIMIT '.$limit.' OFFSET '.$offset;
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $expDates = array();
        foreach ($users as $key => $value) {
            for ($i=1;$i<6;$i++) {
                if ($value['FullLengthNumber'] == (string) $i) {
                    $expDates[$key] = array('FL'.$i.'ExpDate' => $value['ExpDate']);
                }
            }
        }

        $timeZone = new \DateTimeZone('America/New_York');
        foreach ($expDates as $key => $value) {
            $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s',$value[key($value)],$timeZone);
            $users[$key][key($value)] = $dateTime;//Totally Awesome!!
        }

        $users = array_chunk($users, 5);
        foreach ($users as $k => $v) {
            $users[$k] = array_merge($v[0],$v[1],$v[2],$v[3],$v[4]);
        }

        foreach ($users as $key => $value) {
            $users[$key]['account_creation_date'] = \DateTime::createFromFormat('Y-m-d H:i:s', $value['account_creation_date'], $timeZone);
        }

        $spelling = array('One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten');
        foreach ($spelling as $key => $value) {
            if (isset($users[$key])) {
                $users[$key]['spelling'] = $value;
            }
        }

        $isPhil = ($_SESSION['user_id'] == 25) ? true : false;
        $now = new \DateTime('now', new \DateTimeZone('America/New_York'));
        $this->app->render('admin-manage-users.phtml', array(
          'isPhil' => $isPhil,
          'users' => $users,
          'page' => $page,
          'lastPage' => $lastPage,
          'now' => $now,
        ));
    }

    public function updateUserAction()	{
        $page = ($this->app->request->get('page')) ?: 1;

        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();

            //Get administrator requesting the update
            $administrator = $this->getUser($_SESSION['user_id']);
            $isPhil = ($administrator['email'] == 'lenoxhillpremedical@gmail.com') ? true : false;

            //Get the user they are trying to update
            $userToBeUpdated = $this->getUser($post['userId']);

            //If they're trying to update an administrator that is not themselves, don't allow them unless they are Phil
            if ($userToBeUpdated['role'] == 'admin' && !$isPhil && ($userToBeUpdated['user_id'] != $_SESSION['user_id'])) {
                $this->app->flash('message', 'You do not have permission to update another Lenox Hill Premedical administrator.');
                $this->app->redirect('/admin/manage-users?page='.$page);
            }

            if ($userToBeUpdated['email'] == 'lenoxhillpremedical@gmail.com' || $post['email'] == 'lenoxhillpremedical@gmail.com') {
                $this->app->flash('message', 'This user cannot be updated');
                $this->app->redirect('/admin/manage-users?page='.$page);
            }

            //Make sure their exp dates are properly formatted. Build the expDates array
            $expDates = array();
            $tz = new \DateTimeZone('America/New_York');
            foreach (array('fullLength1', 'fullLength2', 'fullLength3', 'fullLength4', 'fullLength5') as $fl) {
                $expDates[$fl] = \DateTime::createFromFormat('Y-m-d H:i', $post[$fl], $tz);
               if (!$expDates[$fl]) {
                   $this->app->flash('message', 'Update unsuccessful. Some of your expiration dates were not in the proper format (e.g. 2017-05-30 06:27)');
                   $this->app->redirect('/admin/manage-users?page='.$page);
               }
            }

            //Update user
            if (!$this->updateUser()) {
                $this->app->flash('message', 'The user data did not update properly.');
                $this->app->redirect('/admin/manage-users?page='.$page);
            }

            //Update expiration dates.
            if (!$this->updateExpDates($userToBeUpdated['user_id'], $expDates)) {
                    $this->app->flash('message', 'The user data updated properly but the expiration dates did not.');
                    $this->app->redirect('/admin/manage-users?page='.$page);
            };

            //Confirmation message
            $confirmationMessage = '<span style="color:green">You have successfully updated ' . $userToBeUpdated['first_name'];
            $confirmationMessage .= ' ' . $userToBeUpdated['last_name'] . ' (UserId = ' . $userToBeUpdated['user_id'] . ').</span>';
            $this->app->flash('message', $confirmationMessage);
        }

        $this->app->redirect('/admin/manage-users?page='.$page);
    }

    public function deleteUserAction() {
        $page = ($this->app->request->get('page')) ?: 1;

        //Get administrator
        $administrator = $this->getUser($_SESSION['user_id']);
        $isPhil = ($administrator['email'] == 'lenoxhillpremedical@gmail.com') ? true : false;


        //What is the user Id of the user to be deleted?
        $userToBeDeletedId = filter_var($this->app->request->get('userToBeDeleted'),FILTER_VALIDATE_INT);

        //Get user they are trying to delete.
        $userToBeDeleted = $this->getUser($userToBeDeletedId);

        //If they're trying to delete an administrator, don't allow them unless they are Phil
        if ($userToBeDeleted['role'] == 'admin' && !$isPhil) {
            $this->app->flash('message', 'You do not have permission to delete another
                Lenox Hill Premedical administrator.'
            );
            $this->app->redirect('/admin/manage-users?page='.$page);
        }

        if ($userToBeDeleted['email'] == 'lenoxhillpremedical@gmail.com') {
            $this->app->flash('message', 'This user cannot be deleted');
            $this->app->redirect('/admin/manage-users?page='.$page);
        }

        //Delete the user.
        $this->deleteUser($userToBeDeletedId);

        //Create confirmation message.
        $confirmationMessage = '<span style="color:green">You have successfully deleted '.$userToBeDeleted['first_name'];
        $confirmationMessage .= ' '.$userToBeDeleted['last_name'].' (UserId = '.$userToBeDeleted['user_id'].').</span>';
        $this->app->flash('message', $confirmationMessage);

        //Redirect administrator back to Manage Users page.
        $this->app->redirect('/admin/manage-users?page='.$page);
    }

	public function manageAdminsAction() {
		//Get administrator and make sure it's Phil
		$phil = $this->getUser($_SESSION['user_id']);
		if ($phil['email'] != 'lenoxhillpremedical@gmail.com') {//not Phil!
			unset($_SESSION);
			$this->app->redirect('/');
		}
		$page = ($this->app->request->get('page')) ?: 1;

		//Check to make sure query parameters are okay
		$newRole = ($this->app->request->get('role')) ?: false;
		$userId = ($this->app->request->get('id')) ?: false;

		//The only way to hit this route is through the admin interface, so if these parameters aren't set,
		//something fishy is going on.
		if (!$newRole || !$userId) {
			unset($_SESSION);
			$this->app->redirect('/');
		}

		//Do not let Phil change himself.
		if ($userId == $phil['user_id']) {//Cannot alter Phil
			$this->app->flash('message', 'Phil, you cannot change your own role!');
			$this->app->redirect('/admin/manage-users?page='.$page);
		}

		//Change the role
		$sql = 'UPDATE user SET role = :newRole WHERE user_id = :userId';
		$stmt = $this->app->db->prepare($sql);
		$stmt->execute(array(':newRole' => $newRole, ':userId' => $userId));

		//Redirect to manage users page.
		$this->app->flash('message', '<span style="color:green">User '.$userId.' changed to '.strtoupper($newRole).'</span>');
		$this->app->redirect('/admin/manage-users?page='.$page);
	}

    public function createTutoringInvoiceAction() {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            //Filter and sanitize post for errors

            //Code here to insert new tutoring session into db
            $startDateTime = \DateTime::createFromFormat('Y-m-d H:i', $post['startDate'].' '.$post['startTime'], new \DateTimeZone('America/New_York'));
            $endDateTime = \DateTime::createFromFormat('Y-m-d H:i', $post['endDate'].' '.$post['endTime'], new \DateTimeZone('America/New_York'));
            $now = new \DateTime();
            $invoiceUrlKey = strtoupper(substr(sha1($_SESSION['user_id'] . '####' . $now->getTimestamp()),0,15));

            $sql = 'INSERT INTO tutoring_invoices (InvoiceUrlKey, Tutoree, StartTime, EndTime, BillableHours, HourlyRate, Tutor, Notes)';
            $sql .= ' VALUES (:invoiceUrlKey, :tutoree, :startTime, :endTime, :billableHours, :hourlyRate, :tutor, :notes)';
            $db = $this->app->db;
            $stmt = $db->prepare($sql);
            $stmt->execute(array(
                ':invoiceUrlKey' => $invoiceUrlKey,
				':tutoree' => $post['tutoree'],
                ':startTime' => $startDateTime->format('Y-m-d H:i:s'),
                ':endTime' => $endDateTime->format('Y-m-d H:i:s'),
                ':billableHours' => $post['hours'],
				':hourlyRate' => $post['hourlyRate'],
                ':tutor' => $_SESSION['user_id'],
                ':notes' => $post['notes'],
            ));
            $invoiceId = $db->lastInsertId();

            //Construct an email to send with appropriate info.

            $sent = $this->sendTutoringEmailInvoice($invoiceId, $post['email'], $post['addressee'], $post['tutoree']);
			if ($sent) {
				$this->app->flash('message', '<span class="text-success">Tutoring invoice created and sent successfully.</span>');
			} else {
				$this->app->flash('message', '<span class="text-danger">Email not sent! Check the database!</span>');
			}
			$this->app->redirect('/admin/create-tutoring-invoice');
        }

        $today = new \DateTime('now', new \DateTimeZone('America/New_York'));
        $this->app->render('admin-create-tutoring-invoice.phtml',
            array(
                'today' => $today,
            )
        );
    }

    public function setApp(Slim $app) {
        $this->app = $app;
    }

/********************************************************************************
 * Admin Services
 *******************************************************************************/
    protected function deleteUser($userId) {
        $sql = 'DELETE FROM user WHERE user_id = :UserId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':UserId' => $userId));
    }

    protected function getUser($userId) {
        $sql = 'SELECT * FROM user WHERE user_id = :UserId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':UserId' => $userId));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result;
    }

    protected function updateUser()
    {
        $post = $this->app->request->post();
        $sql = 'UPDATE user SET first_name = :FirstName, last_name = :LastName, email = :Email, promo_code = :PromoCode, promo_code_discount = :PromoCodeDiscount';
        $sql .= ' WHERE user_id = :UserId';
        $stmt = $this->app->db->prepare($sql);
        $result = $stmt->execute(array(
            ':FirstName' => $post['firstName'],
            ':LastName' => $post['lastName'],
            ':Email' => $post['email'],
			      ':PromoCode' => $post['promoCode'] == '' ? NULL : $post['promoCode'], // w/o this conditional, multiple empty string entries will break db
			      ':PromoCodeDiscount' => $post['promoCodeDiscount'],
            ':UserId' => $post['userId'],
        ));
        return $result;
    }

    protected function updateExpDates($userId, array $expDates)
    {
        $sql = 'UPDATE full_length_access SET ExpDate = :FL1ExpDate WHERE UserId = :UserId AND FullLengthNumber = 1;';
        $sql .= 'UPDATE full_length_access SET ExpDate = :FL2ExpDate WHERE UserId = :UserId AND FullLengthNumber = 2;';
        $sql .= 'UPDATE full_length_access SET ExpDate = :FL3ExpDate WHERE UserId = :UserId AND FullLengthNumber = 3;';
        $sql .= 'UPDATE full_length_access SET ExpDate = :FL4ExpDate WHERE UserId = :UserId AND FullLengthNumber = 4;';
        $sql .= 'UPDATE full_length_access SET ExpDate = :FL5ExpDate WHERE UserId = :UserId AND FullLengthNumber = 5;';

        $stmt = $this->app->db->prepare($sql);
        $result = $stmt->execute(array(
            ':UserId' => $userId,
            ':FL1ExpDate' => $expDates['fullLength1']->format('Y-m-d H:i:s'),
            ':FL2ExpDate' => $expDates['fullLength2']->format('Y-m-d H:i:s'),
            ':FL3ExpDate' => $expDates['fullLength3']->format('Y-m-d H:i:s'),
            ':FL4ExpDate' => $expDates['fullLength4']->format('Y-m-d H:i:s'),
            ':FL5ExpDate' => $expDates['fullLength5']->format('Y-m-d H:i:s'),
        ));
        return $result;
    }

	protected function sendTutoringEmailInvoice($id, $email, $addressee, $tutoree) {
        $sql = 'SELECT InvoiceUrlKey, Tutoree, StartTime, EndTime, BillableHours, HourlyRate, Notes, first_name, last_name';
        $sql .= ' FROM tutoring_invoices JOIN user ON tutoring_invoices.Tutor = user.user_id';
        $sql .= ' WHERE InvoiceId = :id';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(
            ':id' => $id
        ));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $startDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $result['StartTime'],  new \DateTimeZone('America/New_York'));
        $endDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $result['EndTime'], new \DateTimeZone('America/New_York'));


        $textBody = 'Dear '.$addressee.',<br/><br/>';
        $textBody .= 'Please find the following write up about ';
		$textBody .= ($addressee == $tutoree) ? 'your ' : $tutoree.'\'s ';
		$textBody .= 'MCAT<sup>&reg;</sup> tutoring session conducted on '.$startDateTime->format('F jS').'. ';
		$textBody .= ($addressee == $tutoree) ? 'If you were happy with your session, ' : 'If you are satisfied with '.$tutoree.'\'s session, ';
		$textBody .= 'please click ';
		$textBody .= '<a href="https://lenox-hill-premed.appspot.com/tutoring-invoice?token='.$result['InvoiceUrlKey'].'">here</a>';
		$textBody .= ' to pay for the session.<br/><br/>';

		$textBody .= '<table><tbody>';
		$textBody .= '<tr><td><b>Session Date</b></td><td>'.$startDateTime->format('F jS Y').'</td></tr>';
		$textBody .= '<tr><td><b>Start Time</b></td><td>'.$startDateTime->format('g:ia').'</td></tr>';
		$textBody .= '<tr><td><b>End Time</b></td><td>'.$endDateTime->format('g:ia').'</td></tr>';
		$textBody .= '<tr><td><b>MCAT<sup>&reg;</sup>Instructor</b></td><td>'.ucfirst($result['first_name']).' '.ucfirst($result['last_name']).'</td></tr>';
		$textBody .= '<tr><td><b>Instructor\'s Notes</b></td><td style="color:green">'.$result['Notes'].'</td></tr>';
		$textBody .= '<tr><td><b>Billable Hours</b></td><td>'.$result['BillableHours'].'</td></tr>';
		$textBody .= '<tr><td><b>Hourly Rate</b></td><td>$'.$result['HourlyRate'].'/hour</td></tr>';
		$textBody .= '<tr><td><b>Amount Due</b></td><td>'.number_format(floatval($result['BillableHours']*$result['HourlyRate']), 2).'</td></tr></table><br/><br/>';
		$textBody .= 'If you have questions or concerns about this session, please let us know by replying to this email.';
		$textBody .= ' Please note that Lenox Hill Premedical MCAT<sup>&reg;</sup> tutors are not permitted to';
		$textBody .= ' schedule additional tutoring sessions until prior balances have been paid.<br/><br/>';
		$textBody .= 'Thank you.<br/><br/>';
		$textBody .= '- Lenox Hill Premedical';
		try {
			$message = new \google\appengine\api\mail\Message();
			$message->setSubject('MCAT Tutoring Invoice');
			$message->setSender('lenoxhillpremedical@gmail.com');
			$message->addTo($email);
			$message->addBcc('philipmurray80@gmail.com');
			$message->setHtmlBody($textBody);
			$message->send();
		} catch (\Exception $e) {
			return false;
		}
		return true;
    }

}
