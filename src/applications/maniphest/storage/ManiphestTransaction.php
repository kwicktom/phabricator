<?php

final class ManiphestTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_TITLE = 'title';
  const TYPE_STATUS = 'status';
  const TYPE_DESCRIPTION = 'description';
  const TYPE_OWNER  = 'reassign';
  const TYPE_CCS = 'ccs';
  const TYPE_PROJECTS = 'projects';
  const TYPE_PRIORITY = 'priority';
  const TYPE_EDGE = 'edge';
  const TYPE_ATTACH = 'attach';

  public function getApplicationName() {
    return 'maniphest';
  }

  public function getApplicationTransactionType() {
    return ManiphestPHIDTypeTask::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new ManiphestTransactionComment();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $new = $this->getNewValue();
    $old = $this->getOldValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_OWNER:
        if ($new) {
          $phids[] = $new;
        }

        if ($old) {
          $phids[] = $old;
        }
        break;
      case self::TYPE_CCS:
      case self::TYPE_PROJECTS:
        $phids = array_mergev(
          array(
            $phids,
            nonempty($old, array()),
            nonempty($new, array()),
          ));
        break;
      case self::TYPE_EDGE:
        $phids = array_mergev(
          array(
            $phids,
            array_keys(nonempty($old, array())),
            array_keys(nonempty($new, array())),
          ));
        break;
      case self::TYPE_ATTACH:
        $old = nonempty($old, array());
        $new = nonempty($new, array());
        $phids = array_mergev(
          array(
            $phids,
            array_keys(idx($new, 'FILE', array())),
            array_keys(idx($old, 'FILE', array())),
          ));
        break;

    }

    return $phids;
  }

  public function shouldHide() {
    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
      case self::TYPE_DESCRIPTION:
      case self::TYPE_PRIORITY:
        if ($this->getOldValue() === null) {
          return true;
        } else {
          return false;
        }
        break;
    }

    return false;
  }

  public function getActionStrength() {
    switch ($this->getTransactionType()) {
      case self::TYPE_STATUS:
        return 1.3;
      case self::TYPE_OWNER:
        return 1.2;
      case self::TYPE_PRIORITY:
        return 1.1;
    }

    return parent::getActionStrength();
  }


  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_OWNER:
        if ($this->getAuthorPHID() == $new) {
          return 'green';
        } else if (!$new) {
          return 'black';
        } else if (!$old) {
          return 'green';
        } else {
          return 'green';
        }

      case self::TYPE_STATUS:
        if ($new == ManiphestTaskStatus::STATUS_OPEN) {
          return 'green';
        } else {
          return 'black';
        }

      case self::TYPE_PRIORITY:
        if ($old == ManiphestTaskPriority::getDefaultPriority()) {
          return 'green';
        } else if ($old > $new) {
          return 'grey';
        } else {
          return 'yellow';
        }

    }

    return parent::getColor();
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        return pht('Retitled');

      case self::TYPE_STATUS:
        switch ($new) {
          case ManiphestTaskStatus::STATUS_OPEN:
            if ($old === null) {
              return pht('Created');
            } else {
              return pht('Reopened');
            }
          case ManiphestTaskStatus::STATUS_CLOSED_SPITE:
            return pht('Spited');
          case ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE:
            return pht('Merged');
          default:
            return pht('Closed');
        }

      case self::TYPE_DESCRIPTION:
        return pht('Edited');

      case self::TYPE_OWNER:
        if ($this->getAuthorPHID() == $new) {
          return pht('Claimed');
        } else if (!$new) {
          return pht('Up For Grabs');
        } else if (!$old) {
          return pht('Assigned');
        } else {
          return pht('Reassigned');
        }

      case self::TYPE_CCS:
        return pht('Changed CC');

      case self::TYPE_PROJECTS:
        return pht('Changed Projects');

      case self::TYPE_PRIORITY:
        if ($old == ManiphestTaskPriority::getDefaultPriority()) {
          return pht('Triaged');
        } else if ($old > $new) {
          return pht('Lowered Priority');
        } else {
          return pht('Raised Priority');
        }

      case self::TYPE_EDGE:
      case self::TYPE_ATTACH:
        return pht('Attached');
    }

    return parent::getActionName();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_OWNER:
        return 'user';

      case self::TYPE_CCS:
        return 'meta-mta';

      case self::TYPE_TITLE:
        return 'edit';

      case self::TYPE_STATUS:
        switch ($new) {
          case ManiphestTaskStatus::STATUS_OPEN:
            return 'create';
          case ManiphestTaskStatus::STATUS_CLOSED_SPITE:
            return 'dislike';
          case ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE:
            return 'delete';
          default:
            return 'check';
        }

      case self::TYPE_DESCRIPTION:
        return 'edit';

      case self::TYPE_PROJECTS:
        return 'project';

      case self::TYPE_PRIORITY:
        if ($old == ManiphestTaskPriority::getDefaultPriority()) {
          return 'normal-priority';
          return pht('Triaged');
        } else if ($old > $new) {
          return 'lower-priority';
        } else {
          return 'raise-priority';
        }

      case self::TYPE_EDGE:
      case self::TYPE_ATTACH:
        return 'attach';

    }

    return parent::getIcon();
  }



  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        return pht(
          '%s changed the title from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);

      case self::TYPE_DESCRIPTION:
        return pht(
          '%s edited the task description.',
          $this->renderHandleLink($author_phid));

      case self::TYPE_STATUS:
        switch ($new) {
          case ManiphestTaskStatus::STATUS_OPEN:
            if ($old === null) {
              return pht(
                '%s created this task.',
                $this->renderHandleLink($author_phid));
            } else {
              return pht(
                '%s reopened this task.',
                $this->renderHandleLink($author_phid));
            }

          case ManiphestTaskStatus::STATUS_CLOSED_SPITE:
            return pht(
              '%s closed this task out of spite.',
              $this->renderHandleLink($author_phid));
          case ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE:
            return pht(
              '%s closed this task as a duplicate.',
              $this->renderHandleLink($author_phid));
          default:
            $status_name = idx(
              ManiphestTaskStatus::getTaskStatusMap(),
              $new,
              '???');
            return pht(
              '%s closed this task as "%s".',
              $this->renderHandleLink($author_phid),
              $status_name);
        }

      case self::TYPE_OWNER:
        if ($author_phid == $new) {
          return pht(
            '%s claimed this task.',
            $this->renderHandleLink($author_phid));
        } else if (!$new) {
          return pht(
            '%s placed this task up for grabs.',
            $this->renderHandleLink($author_phid));
        } else if (!$old) {
          return pht(
            '%s assigned this task to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($new));
        } else {
          return pht(
            '%s reassigned this task from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }

      case self::TYPE_PROJECTS:
        $added = array_diff($new, $old);
        $removed = array_diff($old, $new);
        if ($added && !$removed) {
          return pht(
            '%s added %d project(s): %s',
            $this->renderHandleLink($author_phid),
            count($added),
            $this->renderHandleList($added));
        } else if ($removed && !$added) {
          return pht(
            '%s removed %d project(s): %s',
            $this->renderHandleLink($author_phid),
            count($removed),
            $this->renderHandleList($removed));
        } else if ($removed && $added) {
          return pht(
            '%s changed project(s), added %d: %s; removed %d: %s',
            $this->renderHandleLink($author_phid),
            count($added),
            $this->renderHandleList($added),
            count($removed),
            $this->renderHandleList($removed));
        } else {
          // This is hit when rendering previews.
          return pht(
            '%s changed projects...',
            $this->renderHandleLink($author_phid));
        }

      case self::TYPE_PRIORITY:
        $old_name = ManiphestTaskPriority::getTaskPriorityName($old);
        $new_name = ManiphestTaskPriority::getTaskPriorityName($new);

        if ($old == ManiphestTaskPriority::getDefaultPriority()) {
          return pht(
            '%s triaged this task as "%s" priority.',
            $this->renderHandleLink($author_phid),
            $new_name);
        } else if ($old > $new) {
          return pht(
            '%s lowered the priority of this task from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old_name,
            $new_name);
        } else {
          return pht(
            '%s raised the priority of this task from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old_name,
            $new_name);
        }

      case self::TYPE_CCS:
        // TODO: Remove this when we switch to subscribers. Just reuse the
        // code in the parent.
        $clone = clone $this;
        $clone->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS);
        return $clone->getTitle();

      case self::TYPE_EDGE:
        // TODO: Remove this when we switch to real edges. Just reuse the
        // code in the parent;
        $clone = clone $this;
        $clone->setTransactionType(PhabricatorTransactions::TYPE_EDGE);
        return $clone->getTitle();

      case self::TYPE_ATTACH:
        $old = nonempty($old, array());
        $new = nonempty($new, array());
        $new = array_keys(idx($new, 'FILE', array()));
        $old = array_keys(idx($old, 'FILE', array()));

        $added = array_diff($new, $old);
        $removed = array_diff($old, $new);
        if ($added && !$removed) {
          return pht(
            '%s attached %d file(s): %s',
            $this->renderHandleLink($author_phid),
            count($added),
            $this->renderHandleList($added));
        } else if ($removed && !$added) {
          return pht(
            '%s detached %d file(s): %s',
            $this->renderHandleLink($author_phid),
            count($removed),
            $this->renderHandleList($removed));
        } else {
          return pht(
            '%s changed file(s), attached %d: %s; detached %d: %s',
            $this->renderHandleLink($author_phid),
            count($added),
            $this->renderHandleList($added),
            count($removed),
            $this->renderHandleList($removed));
        }


    }

    return parent::getTitle();
  }

  public function getTitleForFeed(PhabricatorFeedStory $story) {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_TITLE:
        return pht(
          '%s renamed %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $old,
          $new);

      case self::TYPE_DESCRIPTION:
        return pht(
          '%s edited the description of %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid));

      case self::TYPE_STATUS:
        switch ($new) {
          case ManiphestTaskStatus::STATUS_OPEN:
            if ($old === null) {
              return pht(
                '%s created %s.',
                $this->renderHandleLink($author_phid),
                $this->renderHandleLink($object_phid));
            } else {
              return pht(
                '%s reopened %s.',
                $this->renderHandleLink($author_phid),
                $this->renderHandleLink($object_phid));
            }

          case ManiphestTaskStatus::STATUS_CLOSED_SPITE:
            return pht(
              '%s closed %s out of spite.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
          case ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE:
            return pht(
              '%s closed %s as a duplicate.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
          default:
            $status_name = idx(
              ManiphestTaskStatus::getTaskStatusMap(),
              $new,
              '???');
            return pht(
              '%s closed %s as "%s".',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid),
              $status_name);
        }

      case self::TYPE_OWNER:
        if ($author_phid == $new) {
          return pht(
            '%s claimed %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else if (!$new) {
          return pht(
            '%s placed %s up for grabs.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else if (!$old) {
          return pht(
            '%s assigned %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $this->renderHandleLink($new));
        } else {
          return pht(
            '%s reassigned %s from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }

      case self::TYPE_PROJECTS:
        $added = array_diff($new, $old);
        $removed = array_diff($old, $new);
        if ($added && !$removed) {
          return pht(
            '%s added %d project(s) to %s: %s',
            $this->renderHandleLink($author_phid),
            count($added),
            $this->renderHandleLink($object_phid),
            $this->renderHandleList($added));
        } else if ($removed && !$added) {
          return pht(
            '%s removed %d project(s) from %s: %s',
            $this->renderHandleLink($author_phid),
            count($removed),
            $this->renderHandleLink($object_phid),
            $this->renderHandleList($removed));
        } else if ($removed && $added) {
          return pht(
            '%s changed project(s) of %s, added %d: %s; removed %d: %s',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            count($added),
            $this->renderHandleList($added),
            count($removed),
            $this->renderHandleList($removed));
        }

      case self::TYPE_PRIORITY:
        $old_name = ManiphestTaskPriority::getTaskPriorityName($old);
        $new_name = ManiphestTaskPriority::getTaskPriorityName($new);

        if ($old == ManiphestTaskPriority::getDefaultPriority()) {
          return pht(
            '%s triaged %s as "%s" priority.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $new_name);
        } else if ($old > $new) {
          return pht(
            '%s lowered the priority of %s from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old_name,
            $new_name);
        } else {
          return pht(
            '%s raised the priority of %s from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $old_name,
            $new_name);
        }

      case self::TYPE_CCS:
        // TODO: Remove this when we switch to subscribers. Just reuse the
        // code in the parent.
        $clone = clone $this;
        $clone->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS);
        return $clone->getTitleForFeed($story);

      case self::TYPE_EDGE:
        // TODO: Remove this when we switch to real edges. Just reuse the
        // code in the parent;
        $clone = clone $this;
        $clone->setTransactionType(PhabricatorTransactions::TYPE_EDGE);
        return $clone->getTitleForFeed($story);

      case self::TYPE_ATTACH:
        $old = nonempty($old, array());
        $new = nonempty($new, array());
        $new = array_keys(idx($new, 'FILE', array()));
        $old = array_keys(idx($old, 'FILE', array()));

        $added = array_diff($new, $old);
        $removed = array_diff($old, $new);
        if ($added && !$removed) {
          return pht(
            '%s attached %d file(s) of %s: %s',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            count($added),
            $this->renderHandleList($added));
        } else if ($removed && !$added) {
          return pht(
            '%s detached %d file(s) of %s: %s',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            count($removed),
            $this->renderHandleList($removed));
        } else {
          return pht(
            '%s changed file(s) for %s, attached %d: %s; detached %d: %s',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            count($added),
            $this->renderHandleList($added),
            count($removed),
            $this->renderHandleList($removed));
        }

    }

    return parent::getTitleForFeed($story);
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $view = id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setUser($viewer)
      ->setOldText($old)
      ->setNewText($new);

    return $view->render();
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case self::TYPE_STATUS:
        $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_STATUS;
        break;
      case self::TYPE_OWNER:
        $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_OWNER;
        break;
      case self::TYPE_CCS:
        $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_CC;
        break;
      case self::TYPE_PROJECTS:
        $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_PROJECTS;
        break;
      case self::TYPE_PRIORITY:
        $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_PRIORITY;
        break;
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_COMMENT;
        break;
      default:
        $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_OTHER;
        break;
    }
    return $tags;
  }


}

