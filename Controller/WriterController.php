<?php
namespace Controller;

use Slim\Slim;
use google\appengine\api\cloud_storage\CloudStorageTools;

class WriterController {

    public $app;

    protected $actions = array(
        'index'              => 'managePassagesAction',
        'manage-passages'    => 'managePassagesAction',
        'update-passage'     => 'updatePassageAction',
        'create-passage'     => 'createPassageAction',
        'manage-discretes'   => 'manageDiscretesAction',
        'update-discrete'    => 'updateDiscreteAction',
        'create-discrete'    => 'createDiscreteAction'
    );

    public function dispatchAction($action) {
        $method = $this->actions[$action];
        return $this->$method();
    }

    public function indexAction() {
        $this->app->render('writer-index.phtml', array(
            'writer' => $_SESSION['first_name']
        ));
    }

    public function managePassagesAction() {
        $page = ($this->app->request->get('page')) ?: 1;

        //Get the count of all LHP passages
        $sql = 'SELECT COUNT(*) FROM passages';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute();
        $count = (int) $stmt->fetch()[0];

        //Get the limit and offset
        $limit = 10;
        $lastPage = (int) ceil($count/$limit);
        if ($page < 1) {$page = 1;} elseif ($page > $lastPage) {$page = $lastPage;} //Restrict page numbers.
        $offset = ($page - 1) * $limit;

        //Get the passages and questions
        $sql = 'SELECT * FROM passages JOIN user ON passages.Author = user.user_id';
        $sql .= ' LIMIT '.$limit.' OFFSET '.$offset;
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute();
        $passages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $passageIds = array();
        foreach ($passages as $key => $value) {
            $passages[$key]['PrettyPassageText'] = $this->mapPassageArrayToString($value);
            $passageIds[] = $value['PassageId'];
        }

        $passageIds = implode(',',$passageIds);

        $sql = 'SELECT * FROM questions WHERE PassageId IN ('.$passageIds.')';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute();
        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $prettyQuestions = array();
        foreach ($questions as $key => $value) {
            $prettyQuestions[$key] = $this->mapQuestionToQuestionStringWithImage($value);
        }

        //For twitter bootstrap view template
        $spelling = array('One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten');
        foreach ($spelling as $key => $value) {
            if (isset($passages[$key])) {
                $passages[$key]['spelling'] = $value;
            }
        }

        $this->app->render('writer-manage-passages.phtml',
            array(
                'lastPage' => $lastPage,
                'page'     => $page,
                'passages' => $passages,
                'questions' => $questions,
                'prettyQuestions' => $prettyQuestions,
            ));
    }

    public function updatePassageAction() {
        //Should only get posts to this action method
        $post = $this->app->request->post();

        if (!isset($post)) {
            unset($_SESSION);
            $this->app->redirect('/');
        }

        $page = ($this->app->request->get('page')) ?: 1;

        //Get the author of the passage they are trying to update
        $sql = 'SELECT Author FROM passages WHERE PassageId = :passageId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':passageId' => $post['passageId']));
        $passageAuthorId = $stmt->fetch()['Author'];

        //A writer should not be able to edit the work of another writer.
        if ($_SESSION['role'] != 'admin' && $passageAuthorId != $_SESSION['user_id']) {
            $this->app->flash('message', 'You cannot alter an MCAT passage written by another writer.');
            $this->app->redirect('/writer/manage-passages?page='.$page);
        }

        //Update Passage
        if (!$this->updatePassage($post)) {
            $this->app->flash('message', 'Unable to update passage. Please contact a Lenox Hill Premedical Administrator');
            $this->app->redirect('/writer/manage-passages?page='.$page);
        }

        //Update Questions
        if (!$this->updateQuestions($post['questions'])) {
            $this->app->flash('message', 'Passage was updated, but unable to update questions. Please contact a Lenox Hill Premedical Administrator');
            $this->app->redirect('/writer/manage-passages?page='.$page);
        }

        //Handle images
        //Filter and sanitize names of passage images.

        $images = array();
        //Get the uploaded images
        foreach ($_FILES as $key => $value) {
            if ($value['error'] === 0) {
                $images[$key] = $value;
            }
        }

		if (!empty($images)) {//There is at least one image to be processed.
			$imageTypes = array(
				'questionImage'     => 'question_image.png',
				'answerImage'       => 'answer_image.png',
				'distractor1Image'  => 'distractor_1_image.png',
				'distractor2Image'  => 'distractor_2_image.png',
				'distractor3Image'  => 'distractor_3_image.png',
				'explanationImage'  => 'explanation_image.png',
				'pathology1Image'   => 'pathology_1_image.png',
				'pathology2Image'   => 'pathology_2_image.png',
				'pathology3Image'   => 'pathology_3_image.png'
			);
			//Modify name of each image
			$passageId = $post['passageId'];
			$passageImages = array();
			$questionImages = array();

			//Check to make sure all images are jpeg, png, or gif before converting them to png.
			$allowedTypes = array(IMAGETYPE_PNG);
			foreach ($images as $image) {
				$detectedType = exif_imagetype($image['tmp_name']); //Using an exif function. Not sure what appengine will do
				if (!in_array($detectedType, $allowedTypes)) {
					$this->app->flash('message', 'Images did not upload. Please make sure your images are in the format <strong>png</strong>.');
					$this->app->redirect('/writer/manage-passages?page='.$page);
				}
			}
			foreach ($images as $key => $value) {
				$firstCharacter = substr($key, 0, 1);
				if (!is_numeric($firstCharacter)) { //It's a passage image
					$num = substr($key, 12, 1);
					$images[$key]['name'] = 'p'.$passageId.'_passage_image_'.$num.'.png';
					$passageImages[ucfirst($key)] = $images[$key]['name'];
				} else {
					$firstTwoCharacters = substr($key,0,2);
					if (is_numeric($firstTwoCharacters)) {//to handle double digit keys.
						$firstCharacter = $firstTwoCharacters;
					}
					$questionId = $post['questions'][$firstCharacter]['questionId'];
					$imageType = $imageTypes[substr($key,strlen($firstCharacter))];
					$images[$key]['name'] = 'q'.$questionId.'_'.$imageType;
					$questionImages[$questionId][ucfirst(substr($key,strlen($firstCharacter)))] = $images[$key]['name'];
				}

				//Extremely important for appengine. This sets the cache control metadata of the image you are uploading.
				//If a writer wants to constantly change images, their browser shouldn't cache it, because of this!
				$options = array('gs' => array(
							'Cache-Control' => 'no-cache'
				));
				stream_context_set_default($options);
				move_uploaded_file($value['tmp_name'], getenv('LHP_FULL_LENGTH_BUCKET').$images[$key]['name']);
			}

			//Update passage image names
			if (!empty($passageImages)) {
				$this->updatePassageImageNames($passageImages);
			}

			//Update question image names
			if (!empty($questionImages)){
				$this->updateQuestionImageNames($questionImages);
			}
		}

		$this->app->flash('message', '<span style="color:green">You have successfully updated passage '.$post['passageId'].'.</span>');
		$this->app->redirect('/writer/manage-passages?page='.$page);
    }

    public function createPassageAction() {
        //Create a passage
        $db = $this->app->db;
        $sql = 'INSERT INTO passages (Author) VALUES (:author)';
        $stmt = $db->prepare($sql);
        $stmt->execute(array(':author' => $_SESSION['user_id']));
        $passageId = $db->lastInsertId();

        //Create six questions for passages
        for ($i = 1; $i < 7; $i++) {
            $this->app->db->query('INSERT INTO questions (PassageId) VALUES (' . $passageId . ')');
        }
        //On which page was the passage they just created?
        $sql = 'SELECT COUNT(*) FROM passages';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute();
        $count = (int) $stmt->fetch()[0];

        $limit = 10;
        $lastPage = (int) ceil($count/$limit);
        $this->app->flash('message', '<span style="color:green">You have successfully created a new MCAT passage (PassageId: '.$passageId.'.)</span>');
        $this->app->redirect('/writer/manage-passages?page='.$lastPage);
        //Redirect them to that page.
    }

    public function manageDiscretesAction() {
        $page = ($this->app->request->get('page')) ?: 1;

        //Get the count of all LHP discrete questions
        $sql = 'SELECT COUNT(*) FROM questions WHERE PassageId IS NULL';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute();
        $count = (int) $stmt->fetch()[0];

        //Get the limit and offset
        $limit = 10;
        $lastPage = (int) ceil($count/$limit);
        if ($page < 1) {$page = 1;} elseif ($page > $lastPage) {$page = $lastPage;} //Restrict page numbers.
        $offset = ($page - 1) * $limit;

        //Get the discrete questions
        $sql = 'SELECT * FROM questions WHERE PassageId IS NULL';
        $sql .= ' LIMIT '.$limit.' OFFSET '.$offset;
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute();
        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $prettyQuestions = array();
        foreach ($questions as $key => $value) {
            $prettyQuestions[$key] = $this->mapQuestionToQuestionStringWithImage($value);
        }

        $this->app->render('writer-manage-discretes.phtml',
            array(
                'lastPage' => $lastPage,
                'page'     => $page,
                'questions' => $questions,
                'prettyQuestions' => $prettyQuestions,
            )
        );

    }

    public function updateDiscreteAction() {
        //Should only receive a POST to this action method
        $post = $this->app->request->post();

        if (!isset($post)) {
            unset($_SESSION);
            $this->app->redirect('/');
        }
        $page = ($this->app->request->get('page')) ?: 1;

        if ($_SESSION['role'] != 'admin') {
            $this->app->flash('message', 'You do not have permission to create or alter MCAT discrete questions.');
            $this->app->redirect('/writer/manage-discretes?page='.$page);
        }

        //Update Discrete Question
        $sql = 'UPDATE questions SET QuestionText = :questionText, AnswerText = :answerText, Distractor1Text = :distractor1Text,';
        $sql .= ' Distractor2Text = :distractor2Text, Distractor3Text = :distractor3Text, ExplanationText = :explanationText,';
        $sql .= ' Pathology1Text = :pathology1Text, Pathology2Text = :pathology2Text, Pathology3Text = :pathology3Text';
        $sql .= ' WHERE QuestionId = :questionId';
        $stmt = $this->app->db->prepare($sql);
        $result = $stmt->execute(array(
            ':questionText'     => $post['questionText'],
            ':answerText'       => $post['answerText'],
            ':distractor1Text'  => $post['distractor1Text'],
            ':distractor2Text'  => $post['distractor2Text'],
            ':distractor3Text'  => $post['distractor3Text'],
            ':explanationText'  => $post['explanationText'],
            ':pathology1Text'   => $post['pathology1Text'],
            ':pathology2Text'   => $post['pathology2Text'],
            ':pathology3Text'   => $post['pathology3Text'],
            ':questionId'       => $post['questionId'],
        ));
        if (!$result) {
            $this->app->flash('message', 'Something went wrong while updating discrete question.');
            $this->app->redirect('/writer/manage-discretes?page='.$page);
        }

        //Handle Images
        $images = array();
        foreach ($_FILES as $key => $value) {
            if ($value['error'] === 0) {
                $images[$key] = $value;
            }
        }

        //Check to make sure all images are png format.
        $allowedTypes = array(IMAGETYPE_PNG);
        foreach ($images as $image) {
            $detectedType = exif_imagetype($image['tmp_name']); //Using an exif function. Not sure what appengine will do
            if (!in_array($detectedType, $allowedTypes)) {
                $this->app->flash('message', 'Images did not upload. Please make sure your images are in the format <strong>png</strong>.');
                $this->app->redirect('/writer/manage-discretes?page='.$page);
            }
        }

        $questionId = $post['questionId'];

		if (!empty($images)) {
			$imageTypes = array(
				'questionImage'     => 'question_image.png',
				'answerImage'       => 'answer_image.png',
				'distractor1Image'  => 'distractor_1_image.png',
				'distractor2Image'  => 'distractor_2_image.png',
				'distractor3Image'  => 'distractor_3_image.png',
				'explanationImage'  => 'explanation_image.png',
				'pathology1Image'   => 'pathology_1_image.png',
				'pathology2Image'   => 'pathology_2_image.png',
				'pathology3Image'   => 'pathology_3_image.png'
			);

			$options = array('gs' => array('Cache-Control' => 'no-cache')); //Extremely important for app engine!
			stream_context_set_default($options);

			$imageNames = array();

			foreach ($images as $key => $value) {
				$imageType = $imageTypes[$key];
				$images[$key]['name'] = 'q'.$questionId.'_'.$imageType;
				$imageNames[ucfirst($key)] = $images[$key]['name'];
				move_uploaded_file($value['tmp_name'], 'gs://lhp-full-length-images/'.$images[$key]['name']);
			}

			$string = '';
			$arrayKeys = array_keys($imageNames);
			$lastKey = array_pop($arrayKeys);

			foreach ($imageNames as $key => $value) {
				$string .= $key . ' = \'' . $value . '\''; //Holy semantic craziness Batman!
				if ($key != $lastKey) {
					$string .= ', ';
				}
			}

			$sql = 'UPDATE questions SET '.$string.' WHERE QuestionId = :questionId';
			$stmt = $this->app->db->prepare($sql);
			$stmt->execute(array(':questionId' => $questionId));
        }


        $this->app->flash('message', '<span style="color:green">You have successfully update question '.$questionId.'</span>');
        $this->app->redirect('/writer/manage-discretes?page='.$page);

    }

    public function createDiscreteAction() {
        if ($_SESSION['role'] != 'admin') {
            $this->app->flash('message', 'You do not have permission to create or alter MCAT discrete questions.');
            $this->app->redirect('/writer/manage-discretes');
        }
        //Create a discrete
        $db = $this->app->db;
        $sql = 'INSERT INTO questions (PassageId) VALUES (NULL)';
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $questionId = $db->lastInsertId();

        //On which page was the discrete they just created?
        $sql = 'SELECT COUNT(*) FROM questions WHERE PassageId IS NULL';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute();
        $count = (int) $stmt->fetch()[0];

        //Get the page of the question they just created.
        $limit = 10;
        $lastPage = (int) ceil($count/$limit);

        $this->app->flash('message', '<span style="color:green">You have successfully created a new MCAT discrete (Question Id '.$questionId.')</span>');
        $this->app->redirect('/writer/manage-discretes?page='.$lastPage);
    }

    public function setApp($app) {
        $this->app = $app;
    }

/********************************************************************************
 * Writer Services
*******************************************************************************/

    protected function mapPassageArrayToString(array $passage) {

        $text = $passage['PassageText'];
        if (!(strpos($text, '__PIC1__') === false)) {
			$url = getenv('LHP_FULL_LENGTH_BUCKET').$passage['PassageImage1'];
            $text = str_replace('__PIC1__', '<img src="'.$url.'">', $text);
        }
        if (!(strpos($text, '__PIC2__') === false)) {
          $url = getenv('LHP_FULL_LENGTH_BUCKET').$passage['PassageImage2'];
            $text = str_replace('__PIC2__', '<img src="'.$url.'">', $text);
        }
        if (!(strpos($text, '__PIC3__') === false)) {
          $url = getenv('LHP_FULL_LENGTH_BUCKET').$passage['PassageImage3'];
            $text = str_replace('__PIC3__', '<img src="'.$url.'">', $text);
        }
        if (!(strpos($text, '__PIC4__') === false)) {
      $url = getenv('LHP_FULL_LENGTH_BUCKET').$passage['PassageImage4'];
            $text = str_replace('__PIC4__', '<img src="'.$url.'">', $text);
        }
        if (!(strpos($text, '__PIC5__') === false)) {
      $url = getenv('LHP_FULL_LENGTH_BUCKET').$passage['PassageImage5'];
            $text = str_replace('__PIC5__', '<img src="'.$url.'">', $text);
        }
        return $text;
    }

    protected function mapQuestionToQuestionStringWithImage(array $array) {
        if (!(strpos($array['QuestionText'], '__PIC__') === false)) {
			$url = getenv('LHP_FULL_LENGTH_BUCKET').$array['QuestionImage'];
            $array['QuestionText'] = str_replace('__PIC__', '<img src="'.$url.'">', $array['QuestionText']);
        }
        if (!(strpos($array['AnswerText'], '__PIC__') === false)) {
          $url = getenv('LHP_FULL_LENGTH_BUCKET').$array['AnswerImage'];
            $array['AnswerText'] = str_replace('__PIC__', '<img src="'.$url.'">', $array['AnswerText']);
        }
        if (!(strpos($array['Distractor1Text'], '__PIC__') === false)) {
      $url = getenv('LHP_FULL_LENGTH_BUCKET').$array['Distractor1Image'];
            $array['Distractor1Text'] = str_replace('__PIC__', '<img src="'.$url.'">', $array['Distractor1Text']);
        }
        if (!(strpos($array['Distractor2Text'], '__PIC__') === false)) {
      $url = getenv('LHP_FULL_LENGTH_BUCKET').$array['Distractor2Image'];
            $array['Distractor2Text'] = str_replace('__PIC__', '<img src="'.$url.'">', $array['Distractor2Text']);
        }
        if (!(strpos($array['Distractor3Text'], '__PIC__') === false)) {
      $url = getenv('LHP_FULL_LENGTH_BUCKET').$array['Distractor3Image'];
            $array['Distractor3Text'] = str_replace('__PIC__', '<img src="'.$url.'">', $array['Distractor3Text']);
        }
        if (!(strpos($array['ExplanationText'], '__PIC__') === false)) {
      $url = getenv('LHP_FULL_LENGTH_BUCKET').$array['ExplanationImage'];
            $array['ExplanationText'] = str_replace('__PIC__', '<img src="'.$url.'">', $array['ExplanationText']);
        }
        if (!(strpos($array['Pathology1Text'], '__PIC__') === false)) {
      $url = getenv('LHP_FULL_LENGTH_BUCKET').$array['Pathology1Image'];
            $array['Pathology1Text'] = str_replace('__PIC__', '<img src="'.$url.'">', $array['Pathology1Text']);
        }
        if (!(strpos($array['Pathology2Text'], '__PIC__') === false)) {
          $url = getenv('LHP_FULL_LENGTH_BUCKET').$array['Pathology2Image'];
            $array['Pathology2Text'] = str_replace('__PIC__', '<img src="'.$url.'">', $array['Pathology2Text']);
        }
        if (!(strpos($array['Pathology3Text'], '__PIC__') === false)) {
      $url = getenv('LHP_FULL_LENGTH_BUCKET').$array['Pathology3Image'];
            $array['Pathology3Text'] = str_replace('__PIC__', '<img src="'.$url.'">', $array['Pathology3Text']);
        }
        return $array;
    }

    protected function updatePassage($post) {
        $sql = 'UPDATE passages SET Description = :passageDescription, PassageText = :passageText,';
        $sql .= ' Author = :author WHERE PassageId = :passageId';
        $stmt = $this->app->db->prepare($sql);
        $result = $stmt->execute(array(
            ':passageDescription' => $post['passageDescription'],
            ':passageText'        => $post['passageText'],
            ':author'             => $post['author'],
            ':passageId'          => $post['passageId'],
        ));
        return $result;
    }

    protected function updateQuestions($questions) {
        foreach ($questions as $question) {
            $sql = 'UPDATE questions SET QuestionText = :questionText, AnswerText = :answerText,';
            $sql .= ' Distractor1Text = :distractor1Text,';
            $sql .= ' Distractor2Text = :distractor2Text, Distractor3Text = :distractor3Text,';
            $sql .= ' ExplanationText = :explanationText,';
            $sql .= ' Pathology1Text = :pathology1Text, Pathology2Text = :pathology2Text,';
            $sql .= ' Pathology3Text = :pathology3Text';
            $sql .= ' WHERE QuestionId = :questionId';
            $stmt = $this->app->db->prepare($sql);
            $result = $stmt->execute(array(
                ':questionText'     => $question['questionText'],
                ':answerText'       => $question['answerText'],
                ':distractor1Text'  => $question['distractor1Text'],
                ':distractor2Text'  => $question['distractor2Text'],
                ':distractor3Text'  => $question['distractor3Text'],
                ':explanationText'  => $question['explanationText'],
                ':pathology1Text'   => $question['pathology1Text'],
                ':pathology2Text'   => $question['pathology2Text'],
                ':pathology3Text'   => $question['pathology3Text'],
                ':questionId'       => $question['questionId'],
            ));
            if (!$result) {
                return false;
            }
        }
        return true;
    }

    protected function updatePassageImageNames(array $passageImages) {
        $passageId = $this->app->request->post('passageId');
        $string = '';
        $arrayKeys = array_keys($passageImages);
        $lastKey = array_pop($arrayKeys);
        foreach ($passageImages as $key => $value) {
            $string .= $key . ' = \'' . $value . '\''; //Holy syntax craziness Batman!
            if ($key != $lastKey) {
                $string .= ', ';
            }
        }
        $sql = 'UPDATE passages SET '.$string.' WHERE PassageId = :passageId';
        $stmt = $this->app->db->prepare($sql);
        $stmt->execute(array(':passageId' => $passageId));
    }

    protected function updateQuestionImageNames(array $questionImages) {
        foreach ($questionImages as $questionId => $question) {
            $string = '';
            $arrayKeys = array_keys($question);
            $lastKey = array_pop($arrayKeys);
            foreach ($question as $key => $value) {
                $string .= $key . ' = \'' . $value . '\'';
                if ($key != $lastKey) {
                    $string .= ', ';
                }
            }
            $sql = 'UPDATE questions SET '.$string.' WHERE QuestionId = :questionId';
            $stmt = $this->app->db->prepare($sql);
            $stmt->execute(array(':questionId' => $questionId));
        }
    }
}
