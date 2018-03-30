ALTER TABLE `tbl_tracks` CHANGE `trkMeterUp` `trkMeterUp` DECIMAL(8,3) NULL DEFAULT NULL COMMENT 'Meters ascended';
ALTER TABLE `tbl_tracks` CHANGE `trkMeterDown` `trkMeterDown` DECIMAL(8,3) NULL DEFAULT NULL COMMENT 'Meters descended';
ALTER TABLE `tbl_tracks` CHANGE `trkDistance` `trkDistance` DECIMAL(7,3) NULL DEFAULT NULL COMMENT 'Distance in km';

ALTER TABLE `tbl_tracks` 
ADD `trkCoordTop` INT(11) NOT NULL COMMENT 'Y coord of northern most point of track' AFTER `trkCoordinates`, 
ADD `trkCoordBottom` INT(11) NOT NULL COMMENT 'Y coord of southern most point of track' AFTER `trkCoordTop`, 
ADD `trkCoordLeft` INT(11) NOT NULL COMMENT 'X coord of western most point of track' AFTER `trkCoordBottom`, 
ADD `trkCoordRight` INT(11) NOT NULL COMMENT 'X coord of easter most point of track' AFTER `trkCoordLeft`;

ALTER TABLE `tbl_segments` 
ADD `segCoordTop` INT(11) NOT NULL COMMENT 'Y coord of northern most point of segments' AFTER `segCoordinates`, 
ADD `segCoordBottom` INT(11) NOT NULL COMMENT 'Y coord of southern most point of segments' AFTER `segCoordTop`, 
ADD `segCoordLeft` INT(11) NOT NULL COMMENT 'X coord of western most point of segments' AFTER `segCoordBottom`, 
ADD `segCoordRight` INT(11) NOT NULL COMMENT 'X coord of easter most point of segments' AFTER `segCoordLeft`;

ALTER TABLE `tbl_waypoints` ADD `waypToOfCant` TINYINT(1) NOT NULL AFTER `waypUIAA4000`;
ALTER TABLE `tbl_waypoints` CHANGE `waypToOfCant` `waypToOfCant` VARCHAR(2) NOT NULL;

-- Cleanup coordinates of waypoints
UPDATE `tourdb2_prod`.`tbl_waypoints` 
SET `waypCoordWGS84E` = NULL, `waypCoordWGS84N` = NULL 
WHERE `tbl_waypoints`.`waypCoordWGS84E` = '-0.16171811111111'

