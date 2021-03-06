<?php

final class PhabricatorPeopleProfileViewController
  extends PhabricatorPeopleProfileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $username = $request->getURIData('username');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withUsernames(array($username))
      ->needBadges(true)
      ->needProfileImage(true)
      ->needAvailability(true)
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $this->setUser($user);

    $profile = $user->loadUserProfile();
    $picture = $user->getProfileImageURI();

    $profile_icon = PhabricatorPeopleIconSet::getIconIcon($profile->getIcon());
    $profile_icon = id(new PHUIIconView())
      ->setIcon($profile_icon);
    $profile_title = $profile->getDisplayTitle();

    $header = id(new PHUIHeaderView())
      ->setHeader($user->getFullName())
      ->setSubheader(array($profile_icon, $profile_title))
      ->setImage($picture)
      ->setProfileHeader(true);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $user,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($can_edit) {
      $id = $user->getID();
      $header->setImageEditURL($this->getApplicationURI("picture/{$id}/"));
    }

    $properties = $this->buildPropertyView($user);
    $name = $user->getUsername();

    $feed = $this->buildPeopleFeed($user, $viewer);
    $feed = phutil_tag_div('project-view-feed', $feed);

    $badges = $this->buildBadgesView($user);

    if ($badges) {
      $columns = id(new PHUITwoColumnView())
        ->addClass('project-view-badges')
        ->setMainColumn(
          array(
            $properties,
            $feed,
          ))
        ->setSideColumn(
          array(
            $badges,
          ));
    } else {
      $columns = array($properties, $feed);
    }

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorPeopleProfilePanelEngine::PANEL_PROFILE);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);

    require_celerity_resource('project-view-css');
    $home = phutil_tag(
      'div',
      array(
        'class' => 'project-view-home',
      ),
      array(
        $header,
        $columns,
      ));

    return $this->newPage()
      ->setTitle($user->getUsername())
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $home,
        ));
  }

  private function buildPropertyView(
    PhabricatorUser $user) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($user);

    $field_list = PhabricatorCustomField::getObjectFields(
      $user,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($user, $viewer, $view);

    if (!$view->hasAnyProperties()) {
      return null;
    }

    $view = id(new PHUIBoxView())
      ->setColor(PHUIBoxView::GREY)
      ->appendChild($view)
      ->addClass('project-view-properties');

    return $view;
  }

  private function buildBadgesView(
    PhabricatorUser $user) {

    $viewer = $this->getViewer();
    $class = 'PhabricatorBadgesApplication';
    $box = null;

    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $badge_phids = $user->getBadgePHIDs();
      if ($badge_phids) {
        $badges = id(new PhabricatorBadgesQuery())
          ->setViewer($viewer)
          ->withPHIDs($badge_phids)
          ->withStatuses(array(PhabricatorBadgesBadge::STATUS_ACTIVE))
          ->execute();

        $flex = new PHUIBadgeBoxView();
        foreach ($badges as $badge) {
          $item = id(new PHUIBadgeView())
            ->setIcon($badge->getIcon())
            ->setHeader($badge->getName())
            ->setSubhead($badge->getFlavor())
            ->setQuality($badge->getQuality());
          $flex->addItem($item);
        }

      $box = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Badges'))
        ->appendChild($flex)
        ->setBackground(PHUIBoxView::GREY);
      }
    }

    return $box;
  }

  private function buildPeopleFeed(
    PhabricatorUser $user,
    $viewer) {

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(
      array(
        $user->getPHID(),
      ));
    $query->setLimit(100);
    $query->setViewer($viewer);
    $stories = $query->execute();

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($viewer);
    $builder->setShowHovercards(true);
    $builder->setNoDataString(pht('To begin on such a grand journey, '.
      'requires but just a single step.'));
    $view = $builder->buildView();

    return $view->render();

  }

}
