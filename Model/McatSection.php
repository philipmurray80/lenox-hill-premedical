<?php
namespace Model;

class McatSection
{
	const ANONYMOUS_STUDENT_ID = 89;
	
    const BIO_SECTION_NAME = 'Biological and Biochemical Foundations of Living Systems';
    const PHYS_SECTION_NAME = 'Chemical and Physical Foundations of Biological Systems';
    const PSY_SECTION_NAME = 'Psychological, Social, and Biological Foundations of Behavior';
    const CRIT_SECTION_NAME = 'Critical Analysis and Reasoning Skills';

    const COVER_PAGE_TIME_ALLOWED = 'infinite';
    const TUTORIAL_TIME_ALLOWED = 10;
    const WARNING_PAGE_TIME_ALLOWED = 5;
    const EXAMINEE_AGREEMENT_PAGE_TIME_ALLOWED = 8;
    const DIRECTIONS_PAGE_TIME_ALLOWED = 5;
    const BIO_TIME_ALLOWED = 95;
    const PHYS_TIME_ALLOWED = 95;
    const PSY_TIME_ALLOWED = 95;
    const CRIT_TIME_ALLOWED = 90;
    const BREAK_PAGE_TIME_ALLOWED = 10;
    const LUNCH_PAGE_TIME_ALLOWED = 30;
    const VOID_PAGE_TIME_ALLOWED = 5;
    const FINISH_PAGE_TIME_ALLOWED = 'infinite';

    const BIO_NUMBER_ITEMS = 59;
    const PHYS_NUMBER_ITEMS = 59;
    const PSY_NUMBER_ITEMS = 59;
    const CRIT_NUMBER_ITEMS = 53;

    public $sectionName;
    public $timeAllowed;
    public $numberOfItems;



    public function __construct($section) {
        switch ($section) {
            case 'bio':
                $this->sectionName = McatSection::BIO_SECTION_NAME;
                $this->timeAllowed = McatSection::BIO_TIME_ALLOWED;
                $this->numberOfItems = McatSection::BIO_NUMBER_ITEMS;
                break;
            case 'phys':
                $this->sectionName = McatSection::PHYS_SECTION_NAME;
                $this->timeAllowed = McatSection::PHYS_TIME_ALLOWED;
                $this->numberOfItems = McatSection::PHYS_NUMBER_ITEMS;
                break;
            case 'psy':
                $this->sectionName = McatSection::PSY_SECTION_NAME;
                $this->timeAllowed = McatSection::PSY_TIME_ALLOWED;
                $this->numberOfItems = McatSection::PSY_NUMBER_ITEMS;
                break;
            case 'crit':
                $this->sectionName = McatSection::CRIT_SECTION_NAME;
                $this->timeAllowed = McatSection::CRIT_TIME_ALLOWED;
                $this->numberOfItems = McatSection::CRIT_NUMBER_ITEMS;
                break;
        }
    }

    public static function getNumberOfItems($section) {
        switch ($section) {
            case 'bio':
                return self::BIO_NUMBER_ITEMS;
                break;
            case 'phys':
                return self::PHYS_NUMBER_ITEMS;
                break;
            case 'psy':
                return self::PSY_NUMBER_ITEMS;
                break;
            case 'crit':
                return self::CRIT_NUMBER_ITEMS;
                break;
        }
    }

    public static function getSectionTime($type, $section) {
        $typeArray = array(
            'cover' => McatSection::COVER_PAGE_TIME_ALLOWED,
            'tutorial' => McatSection::TUTORIAL_TIME_ALLOWED,
            'warning' => McatSection::WARNING_PAGE_TIME_ALLOWED,
            'examineeagreement' => McatSection::EXAMINEE_AGREEMENT_PAGE_TIME_ALLOWED,
            'directions' => McatSection::DIRECTIONS_PAGE_TIME_ALLOWED,
            'break' => McatSection::BREAK_PAGE_TIME_ALLOWED,
            'lunch' => McatSection::LUNCH_PAGE_TIME_ALLOWED,
            'void' => McatSection::VOID_PAGE_TIME_ALLOWED,
            'finish' => McatSection::FINISH_PAGE_TIME_ALLOWED,
        );
        foreach ($typeArray as $key => $value) {
            if ($type == $key) {
                return $value*60;
            }
        }
        $sectionArray = array(
            'phys' => McatSection::PHYS_TIME_ALLOWED,
            'crit' => McatSection::CRIT_TIME_ALLOWED,
            'bio' => McatSection::BIO_TIME_ALLOWED,
            'psy' => McatSection::PSY_TIME_ALLOWED
        );
        foreach ($sectionArray as $key => $value) {
            if ($section == $key) {
                return $value * 60;
            }
        }
    }
}