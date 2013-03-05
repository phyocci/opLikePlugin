<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * opLikePlugin actions.
 *
 * @package    OpenPNE
 * @subpackage opLikePlugin
 * @author     tatsuya ichikawa <ichikawa@tejimaya.com>
 */


class likeActions extends opJsonApiActions
{
  public function preExecute()
  {
    $this->memberId = $this->getUser()->getMemberId();
  }

  public function executeSearch(sfWebRequest $request)
  {
    $this->forward400Unless($request['target'], 'foreign_table not specified.');
    $this->forward400Unless($request['target_id'], 'foreign_id not specified.');
    $foreignTable = $request['target'];
    $foreignId = $request['target_id'];

    $this->likes = Doctrine::getTable('Nice')->getNicedList($foreignTable, $foreignId);
    $this->total = Doctrine::getTable('Nice')->getNicedCount($foreignTable, $foreignId);

    $this->requestMemberId = $this->memberId;
  }

  public function executePost(sfWebRequest $request)
  {
    $this->forward400Unless($request['target'], 'target not specified.');
    $this->forward400Unless($request['target_id'], 'target_id not specified.');
    $this->forward400Unless($request['member_id'], 'member_id not specified.');
    $foreignTable = $request['target'];
    $foreignId = $request['target_id'];
    $foreignMemberId = $request['member_id'];

    if (1 < strlen($foreignTable)) $this->forward400('Is at least one character');

    $AlreadyLike = Doctrine::getTable('Nice')->isAlreadyNiced($this->memberId, $foreignTable, $foreignId);
    $this->forward400Unless(!$AlreadyLike, 'It has already been registered');

    $like = new Nice();
    $like->setMemberId($this->memberId);
    $like->setForeignTable($foreignTable);
    $like->setForeignId($foreignId);

    $like->save();
    $this->like = $like;

    $fromMember = $this->getUser()->getMember();
    $toMember = Doctrine::getTable('Member')->findOneById($foreignMemberId);
    $baseUrl = sfContext::getInstance()->getRequest()->getRelativeUrlRoot();
    switch ($foreignTable)
    {
      case 'A':
        $url = $baseUrl.'/timeline/show/id/'.$foreignId;
        break;
      case 'D':
        $url = $baseUrl.'/diary/'.$foreignId;
        break;
      case 'd':
        $diaryComment = Doctrine::getTable('DiaryComment')->findOneById($foreignId);
        $url = $baseUrl.'/diary/'.$diaryComment->getDiaryId();
        break;
      case 'e':
        $eventComment = Doctrine::getTable('CommunityEventComment')->findOneById($foreignId);
        $url = $baseUrl.'/communityEvent/'.$eventComment->getCommunityEventId();
        break;
      case 't':
        $topicComment = Doctrine::getTable('CommunityTopicComment')->findOneById($foreignId);
        $url = $baseUrl.'/communityTopic/'.$topicComment->getCommunityTopicId();
        break;
      default :
        $url = '#';
        break;
    }
    if ($fromMember->getId() !== $toMember->getId())
    {
      opLikePluginUtil::sendNotification($fromMember, $toMember, $url);
    }
  }

  public function executeDelete(sfWebRequest $request)
  {
    $this->forward400Unless($request['target'], 'foreign_table not specified.');
    $this->forward400Unless($request['target_id'], 'foreign_id not specified.');
    $foreignTable = $request['target'];
    $foreignId = $request['target_id'];

    $like = Doctrine::getTable('Nice')->getAlreadyNiced($this->memberId, $foreignTable, $foreignId);
    $this->like = $like->delete()->execute();

    if ($this->like < 1)
    {
      $this->forward400('There is no data');
    }
  }

  public function executeList(sfWebRequest $request)
  {
    $this->forward400Unless($request['target'], 'foreign_table not specified.');
    $this->forward400Unless($request['target_id'], 'foreign_id not specified.');
    $foreignTable = $request['target'];
    $foreignId = $request['target_id'];

    $maxId = null;
    if (isset($request['max_id']))
    {
      is_numeric($request['max_id']) ? $maxId = $request['max_id'] : $this->forward400('max_id is not a number');
    }

    $this->members = Doctrine::getTable('Nice')->getNiceMemberList($foreignTable, $foreignId, $maxId);
  }

  public function executePacketSearch(sfWebRequest $request)
  {
    $this->forward400Unless($request['data'], 'data not specified.');
    $dataList = $request['data'];

    $this->likeList = Doctrine::getTable('Nice')->getPacketNiceMemberList($dataList);
    $this->requestMemberId = $this->memberId;
  }
}
