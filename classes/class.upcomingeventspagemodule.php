<?php

/*	This software is the unpublished, confidential, proprietary, intellectual
	property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
	or used in any manner without expressed written consent from Kim David Software, LLC.
	Kim David Software, LLC owns all rights to this work and intends to keep this
	software confidential so as to maintain its value as a trade secret.

	Copyright 2004-Present, Kim David Software, LLC.

	WARNING! This code is part of the Kim David Software's Coreware system.
	Changes made to this source file will be lost when new versions of the
	system are installed.
*/

/*
%module:upcoming_events%
%module:upcoming_events:start_date=yyyy-mm-dd%
%module:upcoming_events:start_date=yyyy-mm-dd:end_date=yyyy-mm-dd%
%module:upcoming_events:start_date=yyyy-mm-dd:sort_by=[default:start_date]%
%module:upcoming_events:start_date=yyyy-mm-dd:end_date=yyyy-mm-dd:limit=[default:10]%
%module:upcoming_events:start_date=yyyy-mm-dd:end_date=yyyy-mm-dd:limit=[default:10]:group_by_date=[default:false]:header=[default:Upcoming Events]%
%module:upcoming_events:start_date=yyyy-mm-dd:end_date=yyyy-mm-dd:limit=[default:10]:group_by_date=[default:false]:header=[default:Upcoming Events]%
%module:upcoming_events:start_date=yyyy-mm-dd:end_date=yyyy-mm-dd:limit=[default:10]:group_by_date=[default:false]:header=[default:Upcoming Events]:button_text=[default:Register Now]%
*/

class UpcomingEventsPageModule extends PageModule {

    function createContent() {
        $header = (empty($this->iParameters['header']) == false ? $this->iParameters['header'] : "Upcoming Events");
        $groupByDate = (empty($this->iParameters['group_by_date']) == false ? $this->iParameters['group_by_date'] : false);
        $buttonText = (empty($this->iParameters['button_text']) == false ? $this->iParameters['button_text'] : "Register Now");
        $startDate = (!empty($this->iParameters['start_date']) ? date("Y-m-d", strtotime($this->iParameters['start_date'])) : date("Y-m-d"));
        $endDate = (!empty($this->iParameters['end_date']) ? date("Y-m-d", strtotime($this->iParameters['end_date'])) : "");
        $limit = (!empty($this->iParameters['limit']) && is_numeric($this->iParameters['limit']) ? $this->iParameters['limit'] : "10");
        $sortBy = (!empty($this->iParameters['sort_by']) ? $this->iParameters['sort_by'] : "start_date");

        $query = "SELECT start_date, description, product_id FROM events WHERE client_id = ? AND inactive = 0 AND start_date >= ?" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0");
        $parameters = array($GLOBALS['gClientId'], $startDate);
        if (!empty($endDate)) {
            $query .= " and end_date <= ?";
            $parameters[] = $endDate;
        }

        switch ($sortBy) {
            case "start_date":
                $query .= " ORDER BY start_date";
                break;
            case "start_date_desc":
                $query .= " ORDER BY start_date DESC";
                break;
            case "end_date":
                $query .= " ORDER BY end_date";
                break;
            case "end_date_desc":
                $query .= " ORDER BY end_date DESC";
                break;
            case "date_created":
                $query .= " ORDER BY date_created";
                break;
            case "description":
                $query .= " ORDER BY description";
                break;
        }

        $query .= " LIMIT ?";
        $parameters[] = $limit;
        $upcomingEvents = executeQuery($query, $parameters);
        ?>
        <div class="upcoming-events-container">
            <h1> <?= $header ?></h1>
            <div class="upcoming-events-body">
                <?php

                if ($groupByDate == false) {
                    while ($event = getNextRow($upcomingEvents)) {
                        ?>
                        <div class="upcoming-events-row" data-product_id="<?= $event['product_id'] ?>">
                            <div class="upcoming-event-description"> <?= $event['description'] ?> </div>
                            <div class="upcoming-event-date"><?= $event['start_date'] ?></div>
                            <?php if (!empty($event['product_id'])) { ?>
                                <div class="upcoming-event-link">
                                    <a href="/product-details?id=<?= $event['product_id'] ?>"> <?= $buttonText ?></a>
                                </div>
                            <?php } ?>
                        </div>
                        <?php
                    }
                } else {
                    $groupEvents = array();
                    while ($event = getNextRow($upcomingEvents)) {
                        $groupEvents[$event['start_date']][] = array('description' => $event['description'], 'product_id' => $event['product_id']);
                    }
                    foreach ($groupEvents as $eventDate => $events) {
                        ?>
                        <div class="upcoming-events-grouped-row">
                            <div class="upcoming-event-grouped-date"> <?= $eventDate ?> </div>
                            <?php
                            foreach ($events as $event) {
                                ?>
                                <div class="upcoming-event-group-list-description">
                                    <div class="upcoming-event-group-description"> <?= $event['description'] ?></div>
                                    <?php if (!empty($event['product_id'])) { ?>
                                        <div class="upcoming-event-group-link">
                                            <a href="/product-details?id=<?= $event['product_id'] ?>"> <?= $buttonText ?></a>
                                        </div>
                                    <?php } ?>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <!--End upcoming-events-body -->
        </div>
        <!--End upcoming-events-container -->
        <?php
    }
}