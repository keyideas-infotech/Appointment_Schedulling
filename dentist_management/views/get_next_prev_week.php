<?php
$day_from_prev = date('Y-m-d', strtotime($dateToFrom . ' - 7 days'));
$day_to_next = date('Y-m-d', strtotime($dateToFrom . ' + 7 days'));
$today = date('Y-m-d');
?>

<div class="apopointss" style="display: none;position: absolute; left: 0px; top: 0px;">
    <div class="left_arrow getNextPrevWeek" date="<?php echo $day_from_prev; ?>"><img src="<?php echo Template::theme_url("images/arrow_left1.png") ?>" alt=""></div>
    <div class="midel">
        <div class="table-responsive">
            <table class="table table1">
                <thead>
                    <tr>
                        <?php for ($i = 0; $i < 7; $i++): ?>
                            <th>
                                <?php $dt1 = strtotime($dateToFrom . ' + ' . $i . ' days'); ?>
                    <p><strong><?php echo lang('bf_'.date('D', $dt1)); ?></strong></p>
                    <span><?php echo date('d-m-Y', $dt1); ?></span>
                    </th>
                <?php endfor; ?>
                </tr>
                </thead>
                <tbody>                                        
                    <?php if (isset($minTime) && isset($maxTime)): ?>
                        <?php
                        $min = $minTime;
                        $max = $maxTime;
                        $colorClass = array(2, 4, 6);
                        $lolo = 0;
                        ?>
                        <?php while ($min < $max-1): ?>
                            <tr>                                            
                                <?php for ($i = 1; $i <= 7; $i++): ?>                                                                                                                            
                                    <?php $curDateByDayId = date('Y-m-d', strtotime($dateToFrom . ' + ' . ($i - 1) . ' days')); ?>
                                    <?php $curHour = date('G'); ?>
                                    <?php if (isset($dentWorkingDays[$i]) && !in_array($curDateByDayId, $excludeThisDates)): ?>
                                        <?php $start_hour = date('G', strtotime($dentWorkingDays[$i]['start_time'])); ?>
                                        <?php $start_minute = date('i', strtotime($dentWorkingDays[$i]['start_time'])); ?>
                                        <?php $end_hour = date('G', strtotime($dentWorkingDays[$i]['end_time'])); ?>                                                            
                                        <?php $min2s = $start_hour + $lolo; ?>                                                            
                                        <?php if ($min2s >= $start_hour && $min2s < $end_hour): ?>
                                            <?php $h = $min2s < 10 ? '0' . $min2s : $min2s; ?>                                                                
                                            <td class="<?php //echo in_array($i, $colorClass) ? 'colors ' : '';            ?> <?php echo $curDateByDayId == $today ? 'today ' : ''; ?>">
                                                <?php $dateTime = $curDateByDayId . ' ' . date('H:i:s', strtotime($h . ':' . $start_minute . ':00')); ?>
                                                <?php $isdayAfterToday = $curDateByDayId >= $today; ?>
                                                <?php $isTimeAfterNow = $curDateByDayId == $today ? ($min > $curHour ? TRUE : FALSE ) : TRUE; ?>
                                                <?php $is_booked = in_array($dateTime, $patients_appointment_datetime); ?>
                                                <?php $span_title = $isdayAfterToday && $isTimeAfterNow ? ( $is_patient_logged_in ? ($is_booked ? lang('bf_common_booked') : lang('bf_common_make_ur_appos') ) : ($is_dentist_logged_in ? '' : lang('bf_common_login_to_book_ur_appos')) ) : ($is_booked ? lang('bf_common_booked') : '' ); ?>
                                                <?php $span_class = $isdayAfterToday && $isTimeAfterNow ? ( $is_patient_logged_in ? ($is_booked ? 'dateTimePatBooked' : 'dateTimee dateTimeeSelect' ) : ($is_dentist_logged_in ? '' : 'logInPopup dateTimeeSelect') ) : ($is_booked ? 'dateTimeGone dateTimePatBooked' : 'dateTimeGone' ); ?>
                                                <span title="<?php echo $span_title; ?>" class="<?php echo $span_class; ?>" dateTime="<?php echo $dateTime ?>" dateTimePopup="<?php echo lang('bf_'.date('F', strtotime($dateTime))).date(' j, Y', strtotime($dateTime)).' a '.date('h:ia', strtotime($dateTime)); ?>" >
                                                    <?php echo date('h:i A', strtotime($h . ':' . $start_minute . ':00')); ?>
                                                </span>
                                            </td>
                                        <?php else: ?>
                                            <td class="<?php //echo in_array($i, $colorClass) ? 'colors ' : '';            ?> <?php echo $curDateByDayId == $today ? 'today' : ''; ?>"></td>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <td class="<?php //echo in_array($i, $colorClass) ? 'colors ' : '';            ?> <?php echo $curDateByDayId == $today ? 'today' : ''; ?> closed"></td>
                                    <?php endif; ?>                                                                                                                                                                                                                        
                                <?php endfor; ?>
                            </tr>                                                
                            <?php
                            $min++;
                            $lolo++;
                            ?>
                        <?php endwhile; ?>
<?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;"><?php echo lang('bf_common_no_time_available') ?></td>
                        </tr>
<?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="right_arrow getNextPrevWeek" date="<?php echo $day_to_next; ?>"><img src="<?php echo Template::theme_url("images/arrow_right1.png") ?>" alt=""></div>
</div>