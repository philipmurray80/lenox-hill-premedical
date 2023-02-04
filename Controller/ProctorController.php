<?php
namespace Controller;

use Slim\Slim;
use Model\McatSection;
use Model\McatItem;


class ProctorController
{
    public $app;
    public $examId;
    public $fullLengthNumber;
    public $pageNumber;
    public $status;

    public $pageTypes = array(
        'cover-page' => 'coverPageAction',
        'tutorial-page' => 'tutorialPageAction',
        'warning-page' => 'warningPageAction',
        'examineeagreement-page' => 'examineeAgreementPageAction',
        'directions-page' => 'directionsPageAction',
        'content-page' => 'contentPageAction',
        'review-page'  => 'reviewPageAction',
        'break-page'  => 'breakPageAction',
        'lunch-page' => 'lunchPageAction',
        'void-page' => 'voidPageAction',
        'finish-page' => 'finishPageAction',
        'review-directions-page' => 'reviewDirectionsPageAction',
        'review-content-page' => 'reviewContentPageAction',
        'review-review-page' => 'reviewReviewPageAction'
    );

    public function dispatchAction($pageType)
    {
        $method = $this->pageTypes[$pageType];
        return $this->$method();
    }

    public function setApp(Slim $app)
    {
        $this->app = $app;
    }

    public function coverPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $newPageArray = $this->getNewPageArray($post);
            $timeRemaining = (!$newPageArray['IsNewSection']) ? $post['timeRemaining'] : McatSection::getSectionTime($newPageArray['PageType'], $newPageArray['Section']);
            $this->updateExamState($timeRemaining, $newPageArray['PageNumber']);
            $this->app->redirect(
                '/full-length/'.$newPageArray['PageType'].'-page/'.$this->examId.'/'.$this->fullLengthNumber.'/'.$newPageArray['PageNumber']
            );
        }

        //Get user
        $sql = 'SELECT * FROM user WHERE user_id = :userId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':userId' => $_SESSION['user_id']));
        $user = $stmt->fetch(\PDO::FETCH_OBJ);

        $this->app->render(
            'cover-page.phtml',
            array(
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber
            )
        );
    }

    public function tutorialPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $newPageArray = $this->getNewPageArray($post);
            $timeRemaining = (!$newPageArray['IsNewSection']) ? $post['timeRemaining'] : McatSection::getSectionTime($newPageArray['PageType'], $newPageArray['Section']);
            $this->updateExamState($timeRemaining, $newPageArray['PageNumber']);
            $this->app->redirect(
                '/full-length/'.$newPageArray['PageType'].'-page/'.$this->examId.'/'.$this->fullLengthNumber.'/'.$newPageArray['PageNumber']
            );
        }

        $timeRemaining = $this->getTimeRemaining();

        //Is user on first or last page of tutorial? These are hard-coded page numbers!
        $isFirstPage = ($this->pageNumber == 3) ? true : false;
        $isLastPage = ($this->pageNumber == 12) ? true : false;
        $this->app->render(
            'tutorial-page.phtml',
            array(
                'isFirstPage' => $isFirstPage,
                'isLastPage' => $isLastPage,
                'timeRemaining' => $timeRemaining,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber
            )
        );
    }

    public function warningPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $newPageArray = $this->getNewPageArray($post);
            $timeRemaining = (!$newPageArray['IsNewSection']) ? $post['timeRemaining'] : McatSection::getSectionTime($newPageArray['PageType'], $newPageArray['Section']);
            $this->updateExamState($timeRemaining, $newPageArray['PageNumber']);
            $this->app->redirect(
                '/full-length/'.$newPageArray['PageType'].'-page/'.$this->examId.'/'.$this->fullLengthNumber.'/'.$newPageArray['PageNumber']
            );
        }

        $timeRemaining = $this->getTimeRemaining();

        $this->app->render(
            'warning-page.phtml',
            array(
                'timeRemaining' => $timeRemaining,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber,
            )
        );
    }

    public function examineeAgreementPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $newPageArray = $this->getNewPageArray($post);
            $timeRemaining = (!$newPageArray['IsNewSection']) ? $post['timeRemaining'] : McatSection::getSectionTime($newPageArray['PageType'], $newPageArray['Section']);
            $this->updateExamState($timeRemaining, $newPageArray['PageNumber']);
            $this->app->redirect(
                '/full-length/'.$newPageArray['PageType'].'-page/'.$this->examId.'/'.$this->fullLengthNumber.'/'.$newPageArray['PageNumber']
            );
        }

        $timeRemaining = $this->getTimeRemaining();

        $this->app->render(
            'examinee-agreement-page.phtml',
            array(
                'timeRemaining' => $timeRemaining,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber,
            )
        );
    }

    public function directionsPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $newPageArray = $this->getNewPageArray($post);
            $timeRemaining = (!$newPageArray['IsNewSection']) ? $post['timeRemaining'] : McatSection::getSectionTime($newPageArray['PageType'], $newPageArray['Section']);
            $this->updateExamState($timeRemaining, $newPageArray['PageNumber']);
            $this->app->redirect(
                '/full-length/'.$newPageArray['PageType'].'-page/'.$this->examId.'/'.$this->fullLengthNumber.'/'.$newPageArray['PageNumber']
            );
        }

        $timeRemaining = $this->getTimeRemaining();

        //Get section object
        $sql = 'SELECT Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $section = $result['Section'];
        $section = new McatSection($section);

        $this->app->render(
            'directions-page.phtml',
            array(
                'timeRemaining' => $timeRemaining,
                'section' => $section,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber,
            )
        );
    }

    public function contentPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $answers = array();
            //validate submitted answers and build answer array
            foreach ($post as $key => $value) {
                if (is_int($key)) {
                    //Protect against sql injection, white list
                    if (!in_array($value, array('A', 'B', 'C', 'D', 'Am', 'Bm', 'Cm', 'Dm', 'm', 'null'))) {
                        unset($_SESSION);
                        $this->app->redirect('/');
                    }
                    $answers[$key] = $value;
                }
            }

            //save submitted answers
            if (!empty($answers)) {
                $this->saveAnswers($answers);
            }

            //save annotations
            if ($post['annotationChanged'] == 'TRUE') {//they highlighted (or unhighlighted) something
                $this->saveAnnotation($post['annotation'], $post['annotationCount']);
            }

            $newPageArray = $this->getNewPageArray($post);
            $timeRemaining = (!$newPageArray['IsNewSection']) ? $post['timeRemaining'] : McatSection::getSectionTime($newPageArray['PageType'], $newPageArray['Section']);
            $this->updateExamState($timeRemaining, $newPageArray['PageNumber']);
            $redirectString = '/full-length/' . $newPageArray['PageType'] . '-page/' . $this->examId . '/';
            $redirectString .= $this->fullLengthNumber . '/' . $newPageArray['PageNumber'] . (isset($post['previous']) ? '?p' : '');
            $this->app->redirect($redirectString);
        }

        $firstPage = $this->findFirstPageOfSection();
        $previous = (isset($_GET['p'])) ? true : false;
        $timeRemaining = $this->getTimeRemaining();
        $x = $this->retrieveAnnotations();
        $annotationCount = $x['AnnotationCount'];
        $passage = ($x['AnnotationCount'] == 0) ? $this->retrievePassage() : $x['Annotation'];
        $items = $this->retrieveItems();
        $paginationArray = $this->getContentPaginationArray();

        $this->app->render(
            'content-page.phtml',
            array(
                'items' => $items,
                'currentSection' => $firstPage['Section'],
                'previous' => $previous,
                'passage' => $passage,
                'annotationCount' => $annotationCount,
                'timeRemaining' => $timeRemaining,
                'paginationArray' => $paginationArray,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber
            )
        );
    }

    public function reviewPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $newPageArray = $this->getNewPageArray($post);
            $timeRemaining = (!$newPageArray['IsNewSection']) ? $post['timeRemaining'] : McatSection::getSectionTime($newPageArray['PageType'], $newPageArray['Section']);
            $this->updateExamState($timeRemaining, $newPageArray['PageNumber']);
            $redirectString = '/full-length/' . $newPageArray['PageType'] . '-page/' . $this->examId . '/';
            $redirectString .= $this->fullLengthNumber . '/' . $newPageArray['PageNumber'] . (isset($post['previous']) ? '?p' : '');
            $redirectString .= (isset($_GET['q']) ? '?q='.$_GET['q'] : '');
            $redirectString .= (isset($newPageArray['ItemNumber']) ? '?q=' . $newPageArray['ItemNumber'] : '');
            $this->app->redirect($redirectString);
        }

        $timeRemaining = $this->getTimeRemaining();
        $reviewObject = $this->retrieveReviewObject();

        //are there any incomplete questions
        $hasIncompleteQuestions = false;
        foreach ($reviewObject as $item) {
            if ($item['SubmittedAnswer'] == null) {
                $hasIncompleteQuestions = true;
                break;
            }
        }

        //are there any marked questions
        $hasMarkedQuestions = false;
        foreach ($reviewObject as $item) {
            if ($item['Mark'] == 1) {
                $hasMarkedQuestions = true;
                break;
            }
        }

        $this->app->render(
            'review-page.phtml',
            array(
                'reviewObject' => $reviewObject,
                'hasIncompleteQuestions' => $hasIncompleteQuestions,
                'hasMarkedQuestions' => $hasMarkedQuestions,
                'timeRemaining' => $timeRemaining,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber
            )
        );
    }

    public function breakPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $newPageArray = $this->getNewPageArray($post);
            $timeRemaining = (!$newPageArray['IsNewSection']) ? $post['timeRemaining'] : McatSection::getSectionTime($newPageArray['PageType'], $newPageArray['Section']);
            $this->updateExamState($timeRemaining, $newPageArray['PageNumber']);
            $this->app->redirect(
                '/full-length/' . $newPageArray['PageType'] . '-page/' . $this->examId . '/' . $this->fullLengthNumber . '/' . $newPageArray['PageNumber']
            );
        }

        $timeRemaining = $this->getTimeRemaining();

        //Get user
        $sql = 'SELECT * FROM user WHERE user_id = :userId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':userId' => $_SESSION['user_id']));
        $user = $stmt->fetch(\PDO::FETCH_OBJ);

        $this->app->render(
            'break-page.phtml',
            array(
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'timeRemaining' => $timeRemaining,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber
            )
        );
    }

    public function lunchPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $newPageArray = $this->getNewPageArray($post);
            $timeRemaining = (!$newPageArray['IsNewSection']) ? $post['timeRemaining'] : McatSection::getSectionTime($newPageArray['PageType'], $newPageArray['Section']);
            $this->updateExamState($timeRemaining, $newPageArray['PageNumber']);
            $this->app->redirect(
                '/full-length/' . $newPageArray['PageType'] . '-page/' . $this->examId . '/' . $this->fullLengthNumber . '/' . $newPageArray['PageNumber']
            );
        }

        $timeRemaining = $this->getTimeRemaining();

        //Get user
        $sql = 'SELECT * FROM user WHERE user_id = :userId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':userId' => $_SESSION['user_id']));
        $user = $stmt->fetch(\PDO::FETCH_OBJ);

        $this->app->render(
            'lunch-page.phtml',
            array(
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'timeRemaining' => $timeRemaining,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber
            )
        );
    }

    public function voidPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();

            if ($post['void'] == 'VOID') {
                $this->voidExam();
                $this->app->flash('message', 'Your recent MCAT exam has been permanently deleted.');
                $this->app->redirect('/user');
            } else {
                $this->scoreExam();
                $newPageArray = $this->getNewPageArray($post);
                $this->app->redirect(
                    '/full-length/' . $newPageArray['PageType'] . '-page/' . $this->examId . '/' . $this->fullLengthNumber . '/' . $newPageArray['PageNumber']
                );
            }
        }

        $timeRemaining = $this->getTimeRemaining();

        $this->app->render(
            'void-page.phtml',
            array(
                'timeRemaining' => $timeRemaining,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber
            )
        );
    }

    public function finishPageAction()
    {

        //Get user
        $sql = 'SELECT * FROM user WHERE user_id = :userId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':userId' => $_SESSION['user_id']));
        $user = $stmt->fetch(\PDO::FETCH_OBJ);

        $this->app->render(
            'finish-page.phtml',
            array(
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
            )
        );
    }

    public function reviewDirectionsPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $newPageArray = $this->getNewPageArrayForReview($post);
            $this->updateExamState('NULL', $newPageArray['PageNumber']);
            $this->app->redirect(
                '/full-length/' . $newPageArray['PageType'] . '-page/' . $this->examId . '/' . $this->fullLengthNumber . '/' . $newPageArray['PageNumber']
            );
        }

        $firstPagesOfSections = $this->getFirstPagesOfSections();

        //Get section object
        $sql = 'SELECT Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $section = $result['Section'];
        $sectionObject = new McatSection($section);

        $this->app->render(
            'review-directions-page.phtml',
            array(
                'firstPagesOfSections' => $firstPagesOfSections,
                'section' => $section,
                'sectionObject' => $sectionObject,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber
            )
        );
    }

    public function reviewContentPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();

            $answers = array();

            //validate submitted answers and build answer array
            foreach ($post as $key => $value) {
                if (is_int($key)) {
                    //Protect against sql injection, white list
                    if (!in_array($value, array('A', 'B', 'C', 'D', 'Am', 'Bm', 'Cm', 'Dm', 'm', 'null'))) {
                        unset($_SESSION);
                        $this->app->redirect('/');
                    }
                    $answers[$key] = $value;
                }
            }

            //save submitted answers
            if (!empty($answers)) {
                $this->saveAnswers($answers);
            }

            //save annotations
            if ($post['annotationChanged'] == 'TRUE') {//they highlighted (or unhighlighted) something
                $this->saveAnnotation($post['annotation'], $post['annotationCount']);
            }

            $newPageArray = $this->getNewPageArrayForReview($post);
            $this->updateExamState('NULL', $newPageArray['PageNumber']);
            $redirectString = '/full-length/' . $newPageArray['PageType'] . '-page/' . $this->examId . '/';
            $redirectString .= $this->fullLengthNumber . '/' . $newPageArray['PageNumber'] . (isset($post['previous']) ? '?p' : '');
            $this->app->redirect($redirectString);
        }

        $previous = (isset($_GET['p'])) ? true : false;
        $x = $this->retrieveAnnotations();
        $annotationCount = $x['AnnotationCount'];
        $passage = ($x['AnnotationCount'] == 0) ? $this->retrievePassage() : $x['Annotation'];
        $items = $this->retrieveItems(true); //get items WITH SOLUTIONS!
        $firstPagesOfSections = $this->getFirstPagesOfSections();

        //get their section without doing another db query.
        if ($this->pageNumber < $firstPagesOfSections['crit'] && $this->pageNumber > $firstPagesOfSections['phys']) {
            $section = 'phys';
        } elseif ($this->pageNumber < $firstPagesOfSections['bio'] && $this->pageNumber > $firstPagesOfSections['crit']) {
            $section = 'crit';
        } elseif ($this->pageNumber < $firstPagesOfSections['psy'] && $this->pageNumber > $firstPagesOfSections['bio']) {
            $section = 'bio';
        } else {
            $section = 'psy';
        }

        $paginationArray = $this->getContentPaginationArray();

        $this->app->render(
            'review-content-page.phtml',
            array(
                'firstPagesOfSections' => $firstPagesOfSections,
                'previous' => $previous,
                'items' => $items,
                'passage' => $passage,
                'annotationCount' => $annotationCount,
                'section' => $section,
                'paginationArray' => $paginationArray,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber
            )
        );
    }

    public function reviewReviewPageAction()
    {
        if ($this->app->request->isPost()) {
            $post = $this->app->request->post();
            $newPageArray = $this->getNewPageArrayForReview($post);
            $this->updateExamState('NULL', $newPageArray['PageNumber']);
            $redirectString = '/full-length/' . $newPageArray['PageType'] . '-page/' . $this->examId . '/';
            $redirectString .= $this->fullLengthNumber . '/' . $newPageArray['PageNumber'] . (isset($post['previous']) ? '?p' : '');
            $this->app->redirect($redirectString);
        }

        $firstPagesOfSections = $this->getFirstPagesOfSections();
        $reviewObject = $this->retrieveReviewObjectForReview();
        $section = $reviewObject[0]['Section'];

        $this->app->render(
            'review-review-page.phtml',
            array(
                'firstPagesOfSections' => $firstPagesOfSections,
                'reviewObject' => $reviewObject,
                'section' => $section,
                'examId' => $this->examId,
                'fullLengthNumber' => $this->fullLengthNumber,
                'pageNumber' => $this->pageNumber
            )
        );
    }
    /********************************************************************************
     * Proctor Services
     *******************************************************************************/

    protected function getNewPageArray(array $postData)
    {
        $newPageArray = array();
        $newPageArray['IsNewSection'] = false;
        if (isset($postData['timeRemaining']) && ($postData['timeRemaining'] == 0)) {//time has expired
            $result = $this->getNextPageIfTimeHasExpired();
            $newPageArray['PageNumber'] = $result['PageNumber'];
            $newPageArray['PageType'] = $result['PageType'];
            $newPageArray['Section'] = $result['Section'];
            $newPageArray['IsNewSection'] = true;
            return $newPageArray;
        } elseif (isset($postData['next'])) {
            $newPageArray['PageNumber'] = $this->pageNumber + 1;

            //Determine whether their click brought them to a new page type.
            $sql = 'SELECT PageType, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber';
            $sql .= ' AND PageNumber = :newPageNumber UNION SELECT PageType, Section FROM full_length_info';
            $sql .= ' WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber GROUP BY PageType, Section';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(
                ':fullLengthNumber' => $this->fullLengthNumber,
                ':newPageNumber' => $newPageArray['PageNumber'],
                ':pageNumber' => $this->pageNumber));
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            //Determine if they're on the review page or the next section
            if (count($result) != 1 && $result[0]['PageType'] != 'review') {
                $newPageArray['IsNewSection'] = true;
            }
            $newPageArray['PageType'] = $result[0]['PageType'];
            $newPageArray['Section'] = $result[0]['Section'];
            return $newPageArray;
        } elseif (isset($postData['endSection']) || isset($postData['iAgree']) || isset($postData['yes'])) {
            $newPageArray['PageNumber'] = $this->pageNumber + 1;
            $sql = 'SELECT PageType, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :newPageNumber';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':newPageNumber' => $newPageArray['PageNumber']));
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $newPageArray['PageType'] = $result['PageType'];
            $newPageArray['Section'] = $result['Section'];
            //All of these clicks will result in a new section
            $newPageArray['IsNewSection'] = true;
            return $newPageArray;
        } elseif (isset($postData['previous'])) {
            $newPageArray['PageNumber'] = $this->pageNumber - 1;
            $sql = 'SELECT PageType, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :newPageNumber';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':newPageNumber' => $newPageArray['PageNumber']));
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $newPageArray['PageType'] = $result['PageType'];
            $newPageArray['Section'] = $result['Section'];
            return $newPageArray;
        } elseif (isset($postData['review'])) {
            $reviewPage = $this->findReviewPage();
            $newPageArray['PageNumber'] = (int) $reviewPage['PageNumber'];
            $newPageArray['PageType'] = 'review';
            $newPageArray['Section'] = $reviewPage['Section'];
            return $newPageArray;
        } elseif (isset($postData['reviewAll'])) {
            $firstPage = $this->findFirstPageOfSection();
            $newPageArray['PageNumber'] = (int) $firstPage['PageNumber'];
            $newPageArray['PageType'] = 'content';
            $newPageArray['Section'] = $firstPage['Section'];
            return $newPageArray;
        } elseif (isset($postData['reviewIncomplete'])) {
            $incompletePage = $this->findFirstIncompletePageOfSection();
            $newPageArray['PageNumber'] = (int) $incompletePage['PageNumber'];
            $newPageArray['PageType'] = 'content';
            $newPageArray['Section'] = $incompletePage['Section'];
            $newPageArray['ItemNumber'] = $incompletePage['ItemNumber'];
            return $newPageArray;
        } elseif (isset($postData['reviewMarked'])) {
            $markedPage = $this->findFirstMarkedPageOfSection();
            $newPageArray['PageNumber'] = (int) $markedPage['PageNumber'];
            $newPageArray['PageType'] = 'content';
            $newPageArray['Section'] = $markedPage['Section'];
            $newPageArray['ItemNumber'] = $markedPage['ItemNumber'];
            return $newPageArray;
        } elseif (isset($postData['end'])) {
            //These values are hard coded! They'll break if the tutorial section changes.
            $newPageArray['PageNumber'] = 13;
            $newPageArray['PageType'] = 'warning';
            $newPageArray['Section'] = 'warning';
            $newPageArray['IsNewSection'] = true;
            return $newPageArray;
        }
    }

    protected function getNewPageArrayForReview(array $postData)
    {
        $newPageArray = array();

        if (isset($postData['next']) || isset($postData['endSection'])) {
            $newPageArray['PageNumber'] = $this->pageNumber; //start new page number at current page number before loop.
            do {
                $newPageArray['PageNumber']++;
                $sql = 'SELECT PageType, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber LIMIT 1';
                $stmt = $this->app->db->prepare($sql);
                $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $newPageArray['PageNumber']));
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            } while ($result['PageType'] == 'break' || $result['PageType'] == 'lunch'); //skip lunch and break pages
            //send them back one page if they've reached the last page
            if ($result['PageType'] == 'void') {
                $newPageArray['PageNumber']--;
                $sql = 'SELECT PageType, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber LIMIT 1';
                $stmt = $this->app->db->prepare($sql);
                $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $newPageArray['PageNumber']));
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            $newPageArray['PageType'] = 'review-'.$result['PageType']; //must preprend 'review' to the new page type
            $newPageArray['Section'] = $result['Section'];
            return $newPageArray;
        } elseif (isset($postData['previous'])) {
            $newPageArray['PageNumber'] = $this->pageNumber;
            do {
                $newPageArray['PageNumber']--;
                $sql = 'SELECT PageType, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber LIMIT 1';
                $stmt = $this->app->db->prepare($sql);
                $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $newPageArray['PageNumber']));
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            } while ($result['PageType'] == 'break' || $result['PageType'] == 'lunch'); //skip lunch and break pages
            //send them forward one page if they've reached the examinee agreement page
            if ($result['PageType'] == 'warning') {//they've reached the first page
                $newPageArray['PageNumber']++; //increment page number
                $sql = 'SELECT PageType, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber LIMIT 1';
                $stmt = $this->app->db->prepare($sql);
                $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $newPageArray['PageNumber']));
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            $newPageArray['PageType'] = 'review-'.$result['PageType'];
            $newPageArray['Section'] = $result['Section'];
            return $newPageArray;
        } elseif (isset($postData['review'])) {
            $reviewPage = $this->findReviewPage();
            $newPageArray['PageNumber'] = (int) $reviewPage['PageNumber'];
            $newPageArray['PageType'] = 'review-review';
            $newPageArray['Section'] = $reviewPage['Section'];
            return $newPageArray;
        } elseif (isset($postData['reviewAll'])) {
            $firstPage = $this->findFirstPageOfSection();
            $newPageArray['PageNumber'] = (int) $firstPage['PageNumber'];
            $newPageArray['PageType'] = 'review-content';
            $newPageArray['Section'] = $firstPage['Section'];
            return $newPageArray;
        }
    }

    protected function retrieveReviewObject()
    {
        //do two separate queries to avoid joining on the submitted_answers table
        $sql = 'SELECT Section, ItemNumber, PageNumber FROM full_length_info';
        $sql .= ' WHERE FullLengthNumber = :fullLengthNumber AND PageType = \'content\' AND Section = ';
        $sql .= '(SELECT Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber)';
        $sql .= ' ORDER BY ItemNumber ASC';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        //get first and last item
        $firstItem = $items[0]['ItemNumber'];
        $lastItem = end($items)['ItemNumber']; //'end' gets last element of array

        //get submitted answers for items
        $sql = 'SELECT SubmittedAnswer, Mark FROM submitted_answers WHERE ExamId = :examId';
        $sql .= ' AND ItemNumber BETWEEN :firstItem AND :lastItem ORDER BY ItemNumber ASC';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $this->examId, ':firstItem' => $firstItem, ':lastItem' => $lastItem));
        $submittedAnswers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        //factor in section offset and merge items with submitted answers.
        $offset = $this->getItemOffset($items[0]['Section']);

        for ($i=0; $i<count($items); $i++) {
            $items[$i] = array_merge($items[$i], $submittedAnswers[$i]);
            $items[$i]['OffsetItemNumber'] = $items[$i]['ItemNumber'] - $offset;
        }
        return $items;
    }

    protected function retrieveReviewObjectForReview()
    {
        //do two separate queries to avoid joining on the submitted_answers table
        $sql = 'SELECT Section, ItemNumber, PageNumber, Answer FROM full_length_info';
        $sql .= ' WHERE FullLengthNumber = :fullLengthNumber AND PageType = \'content\' AND Section = ';
        $sql .= '(SELECT Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber)';
        $sql .= ' ORDER BY ItemNumber ASC';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        //get first and last item
        $firstItem = $items[0]['ItemNumber'];
        $lastItem = end($items)['ItemNumber']; //'end' gets last element of array

        //get submitted answers for items
        $sql = 'SELECT SubmittedAnswer, Mark FROM submitted_answers WHERE ExamId = :examId';
        $sql .= ' AND ItemNumber BETWEEN :firstItem AND :lastItem ORDER BY ItemNumber ASC';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $this->examId, ':firstItem' => $firstItem, ':lastItem' => $lastItem));
        $submittedAnswers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        //factor in section offset and merge items with submitted answers.
        $offset = $this->getItemOffset($items[0]['Section']);

        //merge the arrays and add the 'correct' key.
        for ($i=0; $i<count($items); $i++) {
            if ($items[$i]['Answer'] == $submittedAnswers[$i]['SubmittedAnswer']) {
                $items[$i]['Correct'] = 'Correct';
            } else {
                $items[$i]['Correct'] = 'Incorrect';
            }
            $items[$i] = array_merge($items[$i], $submittedAnswers[$i]);
            $items[$i]['OffsetItemNumber'] = $items[$i]['ItemNumber'] - $offset;
        }
        return $items;
    }

    protected function getContentPaginationArray()
    {
        $sql = 'SELECT MIN(ItemNumber) as min, MAX(ItemNumber) as max, Section FROM full_length_info';
        $sql .= ' WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber GROUP BY Section';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $offset = $this->getItemOffset($result['Section']);
        $totalItems = McatSection::getNumberOfItems($result['Section']);
        return array(
            'min' => $result['min'] - $offset,
            'max' => $result['max'] - $offset,
            'total' => $totalItems
        );
    }

    protected function retrieveItems($review = false)
    {
        //Do two separate db queries in order to avoid joining on the submitted_answers table.
        //get the items
        $sql = 'SELECT full_length_info.ItemNumber, Section, Answer, Distractor1, Distractor2, Distractor3, QuestionText, AnswerText, Distractor1Text, Distractor2Text, Distractor3Text,';
        if ($review) {
            $sql .= ' ExplanationText, Pathology1Text, Pathology2Text, Pathology3Text,';
        }
        $sql .= ' QuestionImage, AnswerImage, Distractor1Image, Distractor2Image, Distractor3Image';
        if ($review) {
            $sql .= ', ExplanationImage, Pathology1Image, Pathology2Image, Pathology3Image';
        }
        $sql .= ' FROM full_length_info JOIN questions ON full_length_info.QuestionId = questions.QuestionId';
        $sql .= ' WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber ORDER BY full_length_info.ItemNumber ASC';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        //prepare an sql string (e.g. (8,9,10,11,12,13)) to use for querying the submitted_answers table.
        $itemNumbers = '(';
        foreach ($items as $key => $value) {
            $itemNumbers .= $value['ItemNumber'].',';
        }
        $itemNumbers = substr_replace($itemNumbers, ')', -1, 1); //Nifty trick to remove the last comma and replace it with a ).

        //get submitted answers for the items
        $sql = 'SELECT ItemNumber, SubmittedAnswer, Mark FROM submitted_answers WHERE ExamId = :examId AND ItemNumber IN '.$itemNumbers;
        $sql .= ' ORDER BY ItemNumber ASC';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $this->examId));
        $submittedAnswers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        //attach the submitted answers to the items array (assumes that items and submitted answers have exactly the same ordering)
        for ($i=0; $i<count($items); $i++) {
            $items[$i] = array_merge($items[$i], $submittedAnswers[$i]);
        }

        //items are stored in db from 1-230. Need offset to display items on per-section basis.
        $itemOffset = $this->getItemOffset($items[0]['Section']);


        //build the item objects
        $mcatItems = array();
        foreach ($items as $item) {
            $mcatItem = new McatItem;
            $mcatItem->itemNumber = (int) $item['ItemNumber'];
            $mcatItem->offsetItemNumber = $item['ItemNumber'] - $itemOffset;
            $mcatItem->q = $this->mapItemStringToItemStringWithImage(array($item['QuestionText'], $item['QuestionImage']));
            $mcatItem->a = $this->mapItemStringToItemStringWithImage($this->findAnswerChoice('A', $item));
            $mcatItem->b = $this->mapItemStringToItemStringWithImage($this->findAnswerChoice('B', $item));
            $mcatItem->c = $this->mapItemStringToItemStringWithImage($this->findAnswerChoice('C', $item));
            $mcatItem->d = $this->mapItemStringToItemStringWithImage($this->findAnswerChoice('D', $item));
            if ($review) {
                $mcatItem->solutionA = $this->mapItemStringToItemStringWithImage($this->findSolution('A', $item));
                $mcatItem->solutionB = $this->mapItemStringToItemStringWithImage($this->findSolution('B', $item));
                $mcatItem->solutionC = $this->mapItemStringToItemStringWithImage($this->findSolution('C', $item));
                $mcatItem->solutionD = $this->mapItemStringToItemStringWithImage($this->findSolution('D', $item));
                $mcatItem->correctAnswer = $item['Answer'];
            }

            //change the 'SubmittedAnswer' element to account for marking.
            if ($item['SubmittedAnswer'] != null) {
                if ($item['Mark'] == 1) {
                    $item['SubmittedAnswer'] = $item['SubmittedAnswer'].'m';
                }
            } else {
                if ($item['Mark'] == 1) {
                    $item['SubmittedAnswer'] = 'm';
                }
            }
            $mcatItem->answer = $item['SubmittedAnswer'];

            //append the object to the mcatItems array
            $mcatItems[] = $mcatItem;
        }

        return $mcatItems;
    }

    protected function findAnswerChoice($letter, array $itemArray)
    {
        if ($itemArray['Answer'] == $letter) {
            return array($itemArray['AnswerText'], $itemArray['AnswerImage']);
        } elseif ($itemArray['Distractor1'] == $letter) {
            return array($itemArray['Distractor1Text'], $itemArray['Distractor1Image']);
        } elseif ($itemArray['Distractor2'] == $letter) {
            return array($itemArray['Distractor2Text'], $itemArray['Distractor2Image']);
        } elseif ($itemArray['Distractor3'] == $letter) {
            return array($itemArray['Distractor3Text'], $itemArray['Distractor3Image']);
        }
    }

    protected function findSolution($letter, array $itemArray)
    {
        if ($itemArray['Answer'] == $letter) {
            return array($itemArray['ExplanationText'], $itemArray['ExplanationImage']);
        } elseif ($itemArray['Distractor1'] == $letter) {
            return array($itemArray['Pathology1Text'], $itemArray['Pathology1Image']);
        } elseif ($itemArray['Distractor2'] == $letter) {
            return array($itemArray['Pathology2Text'], $itemArray['Pathology2Image']);
        } elseif ($itemArray['Distractor3'] == $letter) {
            return array($itemArray['Pathology3Text'], $itemArray['Pathology3Image']);
        }
    }

    protected function mapItemStringToItemStringWithImage(array $array)
    {
        if (!(strpos($array[0], '__PIC__') === false)) {
            $url = getenv('LHP_FULL_LENGTH_BUCKET') . $array[1];
            //$url = CloudStorageTools::getPublicUrl('gs://lhp-full-length-images/'.$array[1], true);
            return str_replace('__PIC__', '<img src="'.$url.'">', $array[0]);
        }
        return $array[0];
    }

    protected function getItemOffset($section)
    {
        switch ($section) {
            case 'phys': return 0; break;
            case 'crit': return McatSection::getNumberOfItems('phys'); break;
            case 'bio': return McatSection::getNumberOfItems('phys') + McatSection::getNumberOfItems('crit'); break;
            case 'psy': return McatSection::getNumberOfItems('phys') + McatSection::getNumberOfItems('crit') + McatSection::getNumberOfItems('bio'); break;
        }
    }

    protected function retrievePassage()
    {
        $sql = 'SELECT PassageText, PassageImage1, PassageImage2, PassageImage3, PassageImage4, PassageImage5';
        $sql .= ' FROM full_length_info JOIN passages ON full_length_info.PassageId = passages.PassageId';
        $sql .= ' WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber LIMIT 1';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (empty($result)) {//means that it's a discrete set, no passage
            return '<p style="text-align:center;line-height:400px">The following questions are not based on a passage.</p>';
        } else {
            return $this->mapPassageArrayToString($result);
        }
    }

    protected function retrieveAnnotations()
    {
        $sql = 'SELECT Annotation, AnnotationCount FROM annotations';
        $sql .= ' WHERE ExamId = :examId AND PageNumber = :pageNumber';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $this->examId, ':pageNumber' => $this->pageNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result;
    }

    protected function mapPassageArrayToString(array $passage)
    {
        $text = $passage['PassageText'];
        if (!(strpos($text, '__PIC1__') === false)) {
            $url = 'public/images/' . $passage['PassageImage1'];
            $text = str_replace('__PIC1__', '<img src="'.$url.'">', $text);
        }
        if (!(strpos($text, '__PIC2__') === false)) {
            $url = 'public/images/' .  $passage['PassageImage2'];
            $text = str_replace('__PIC2__', '<img src="'.$url.'">', $text);
        }
        if (!(strpos($text, '__PIC3__') === false)) {
            $url = 'public/images/' . $passage['PassageImage3'];
            $text = str_replace('__PIC3__', '<img src="'.$url.'">', $text);
        }
        if (!(strpos($text, '__PIC4__') === false)) {
            $url = 'public/images/' . $passage['PassageImage4'];
            $text = str_replace('__PIC4__', '<img src="'.$url.'">', $text);
        }
        if (!(strpos($text, '__PIC5__') === false)) {
            $url = 'public/images/' . $passage['PassageImage5'];
            $text = str_replace('__PIC5__', '<img src="'.$url.'">', $text);
        }
        return $text;
    }

    protected function saveAnswers(array $answers)
    {
        $sql = '';
        foreach ($answers as $item => $answer) {
            if (strpos($answer, 'm') === 1) {
                $sql .= 'UPDATE submitted_answers SET SubmittedAnswer = \''.substr($answer, 0, 1).'\'';
                $sql .= ', Mark = 1';
            } elseif (strpos($answer, 'm') === 0) {
                $sql .= 'UPDATE submitted_answers SET SubmittedAnswer = NULL, Mark = 1';
            } elseif ($answer == 'null') {
                $sql .= 'UPDATE submitted_answers SET SubmittedAnswer = NULL, Mark = 0';
            } else {
                $sql .= 'UPDATE submitted_answers SET SubmittedAnswer = \''.$answer.'\'';
                $sql .= ', Mark = 0';
            }
            $sql .= ' WHERE ExamId = '.$this->examId;
            $sql .= ' AND ItemNumber = '.$item;
            $sql .= '; ';
        }
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute();
    }

    protected function saveAnnotation($annotation, $annoCount)
    {
        $sql = 'UPDATE annotations SET Annotation = :annotation, AnnotationCount = :annoCount';
        $sql .= ' WHERE ExamId = :examId AND PageNumber = :pageNumber';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(
            ':annotation' => $annotation,
            ':annoCount' => $annoCount,
            ':examId' => $this->examId,
            ':pageNumber' => $this->pageNumber,
        ));
    }

    protected function getTimeRemaining()
    {
        $sql = 'SELECT TimeRemaining FROM exams WHERE ExamId = :examId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $this->examId));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['TimeRemaining'];
    }

    protected function updateExamState($timeRemaining, $currentPageNumber)
    {
        $sql = 'UPDATE exams SET CurrentPageNumber = :currentPageNumber, TimeRemaining = :timeRemaining WHERE examId = :examId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':currentPageNumber' => $currentPageNumber, ':timeRemaining' => $timeRemaining, ':examId' => $this->examId));
    }

    protected function findFirstMarkedPageOfSection()
    {
        //Don't want to use joins on submitted_answers table because it can get big.
        $sql = 'SELECT MIN(ItemNumber) as FirstMarkedItem FROM submitted_answers WHERE ExamId = :examId AND Mark = 1 AND ItemNumber IN ';
        $sql .= '(SELECT ItemNumber FROM full_length_info WHERE PageType = \'content\' AND FullLengthNumber = :fullLengthNumber AND Section = ';
        $sql .= '(SELECT Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber LIMIT 1))';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $this->examId, ':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$result) {// They've clicked on 'Review Marked', but they don't have any marked questions. Return them to the review page.
            //really don't need this, because controller will only display "review marked" button if they have marked items.
            return $this->findReviewPage();
        }

        $firstMarkedItem = $result['FirstMarkedItem'];

        $sql = 'SELECT PageNumber, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND ItemNumber = '.$firstMarkedItem;
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $result['ItemNumber'] = $firstMarkedItem;
        return $result;
    }

    protected function findFirstIncompletePageOfSection()
    {
        //Don't want to use joins on submitted_answers table because it can get big. Strategy...
        //1)Get lowest incomplete item of section. 2)Get page number associated with lowest incomplete item 3)Merge them.

        $sql = 'SELECT MIN(ItemNumber) as FirstIncompleteItem FROM submitted_answers WHERE ExamId = :examId AND SubmittedAnswer IS NULL AND ItemNumber IN ';
        $sql .= '(SELECT ItemNumber FROM full_length_info WHERE PageType = \'content\' AND FullLengthNumber = :fullLengthNumber AND Section = ';
        $sql .= '(SELECT Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber LIMIT 1))';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $this->examId, ':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$result) {// They've clicked on 'Review Incomplete', but they don't have any incomplete questions. Return them to the review page.
            //This code might not be needed. There will be no 'Review Incomplete' button if there are no incomplete questions.
            return $this->findReviewPage();
        }

        $firstIncompleteItem = $result['FirstIncompleteItem'];

        $sql = 'SELECT PageNumber, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND ItemNumber = '.$firstIncompleteItem;
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $result['ItemNumber'] = $firstIncompleteItem;
        return $result;
    }

    protected function findFirstPageOfSection()
    {
        //prime candidate for caching. This should only change when the actual full_length_info table gets changed.
        $sql = 'SELECT PageNumber, Section FROM full_length_info';
        $sql .= ' WHERE FullLengthNumber = :fullLengthNumber AND PageType = \'content\' AND Section = ';
        $sql .= '(SELECT Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageNumber = :pageNumber LIMIT 1)';
        $sql .= ' ORDER BY PageNumber ASC LIMIT 1';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result;
    }

    protected function findReviewPage()
    {
        $sql = 'SELECT PageNumber, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber';
        $sql .= ' AND PageType = \'review\' AND PageNumber > :pageNumber LIMIT 1';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result;
    }

    protected function getNextPageIfTimeHasExpired()
    {
        $sql = 'SELECT PageNumber, PageType, Section FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber ';
        $sql .= 'AND PageNumber > :pageNumber AND (PageType, Section) != (SELECT PageType, Section FROM full_length_info ';
        $sql .= 'WHERE PageNumber = :pageNumber LIMIT 1) AND PageType != \'review\' ORDER BY PageNumber ASC LIMIT 1';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':pageNumber' => $this->pageNumber));
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    protected function scoreExam()
    {
        //This query joins on the submitted_answers table. It should be longest query in the entire full-length module. Use it for benchmarking.
        //Also a great candidate for appengine task queue, because student gets redirected to finish page, then has to click back to home page before seeing score.

        //calculate raw scores
        $rawScore = array();
        foreach (array('phys', 'crit', 'bio', 'psy') as $section) {
            $sql = 'SELECT COUNT(*) as raw_score FROM submitted_answers INNER JOIN exams ON submitted_answers.ExamId = exams.ExamId';
            $sql .= ' JOIN full_length_info ON exams.FullLengthNumber = full_length_info.FullLengthNumber';
            $sql .= ' WHERE exams.ExamId = :examId AND submitted_answers.ItemNumber = full_length_info.ItemNumber';
            $sql .= ' AND full_length_info.Section = :section AND submitted_answers.SubmittedAnswer = full_length_info.Answer';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':examId' => $this->examId, ':section' => $section));
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $rawScore[$section] = $result['raw_score'];
        }

        //input raw scores
        $sql = 'UPDATE exams SET PhysRawScore = :physRawScore, CritRawScore = :critRawScore, BioRawScore = :bioRawScore, PsyRawScore = :psyRawScore';
        $sql .= ' WHERE ExamId = :examId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(
            ':physRawScore' => $rawScore['phys'],
            ':critRawScore' => $rawScore['crit'],
            ':bioRawScore'  => $rawScore['bio'],
            ':psyRawScore'  => $rawScore['psy'],
            ':examId'       => $this->examId,
        ));

        //get scaled scores
        $scaledScore = array();
        foreach ($rawScore as $section => $score) {
            $sql = 'SELECT ScaledScore FROM score_scales WHERE FullLengthNumber = :fullLengthNumber';
            $sql .= ' AND Section = :section AND RawScore = :rawScore';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber, ':section' => $section, ':rawScore' => $score));
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $scaledScore[$section] = $result['ScaledScore'];
        }

        //update the exam: set the scores, the time remaining, and the current page to the first phys directions page.
        $sql = 'UPDATE exams SET PhysScore = :physScore, CritScore = :critScore, BioScore = :bioScore, PsyScore = :psyScore, ';
        $sql .= 'Status = \'scored\', TimeRemaining = NULL, CurrentPageNumber = ';
        $sql .= '(SELECT PageNumber FROM full_length_info WHERE FullLengthNumber = :fullLengthNumber AND PageType = \'directions\' AND Section = \'phys\')';
        $sql .= ' WHERE ExamId = :examId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(
            ':physScore' => $scaledScore['phys'],
            ':critScore' => $scaledScore['crit'],
            ':bioScore' => $scaledScore['bio'],
            ':psyScore' => $scaledScore['psy'],
            ':fullLengthNumber' => $this->fullLengthNumber,
            ':examId' => $this->examId
        ));
    }

    protected function voidExam()
    {
        $sql = 'DELETE FROM exams WHERE ExamId = :examId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':examId' => $this->examId));
    }

    public function getFirstPagesOfSections()
    {
        $sql = 'SELECT Section, PageNumber FROM full_length_info WHERE PageType = \'directions\' AND FullLengthNumber = :fullLengthNumber';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':fullLengthNumber' => $this->fullLengthNumber));
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $array = array();
        foreach ($result as $key => $value) {
            $array[$value['Section']] = $value['PageNumber'];
        }
        return $array;
    }
}
