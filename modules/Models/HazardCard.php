<?php
namespace GT\Models;

/*
 * HazardCard: interface for cards that have hazards
 */
interface HazardCard
{
  public function getCurrentHazard($progress = null);
}
