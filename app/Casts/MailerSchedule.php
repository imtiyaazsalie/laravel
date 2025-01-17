<?php

namespace App\Casts{
    use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
    use Illuminate\Database\Eloquent\Model;

    class MailerSchedule implements CastsAttributes
    {
        /**
         * Get the serialized representation of the value.
         *
         * @return mixed
         */
        public function get(Model $model, string $key, mixed $value, array $attributes)
        {
            return unserialize($value);
        }

        public function set($model, $key, $value, $attributes)
        {
            return serialize($value);
        }
    }}

namespace App\Model\Crm{
    class Schedule
    {
        const TYPE_NOW = 'now';

        const TYPE_ONCE_OFF = 'once_off';

        const TYPE_DAILY = 'daily';

        const TYPE_WEEKLY = 'weekly';

        const TYPE_MONTHLY = 'monthly';

        const TYPE_EVENT = 'event';

        const TYPE_ACTION = 'action';

        protected $type;

        protected $conditions;

        /**
         * @return mixed
         */
        public function getConditions()
        {
            return $this->conditions;
        }

        /**
         * @param  mixed  $conditions
         * @return Schedule
         */
        public function setConditions($conditions)
        {
            $this->conditions = $conditions;

            return $this;
        }

        /**
         * @return string
         */
        public function getType()
        {
            return $this->type;
        }

        /**
         * @param  string  $type
         * @return Schedule
         */
        public function setType($type)
        {
            $this->type = $type;

            return $this;
        }

        public function __construct()
        {
            $this->conditions = [];
        }
    }

    class ActionSchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'action';
        }

        protected $action;

        /**
         * @return mixed
         */
        public function getAction()
        {
            return $this->action;
        }

        /**
         * @param  mixed  $action
         * @return ActionSchedule
         */
        public function setAction($action)
        {
            $this->action = $action;

            return $this;
        }
    }

    class DailySchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'daily';
        }

        protected $time;

        protected $amPm;

        /**
         * @return mixed
         */
        public function getAmPm()
        {
            return $this->amPm;
        }

        /**
         * @param  mixed  $amPm
         * @return DailySchedule
         */
        public function setAmPm($amPm)
        {
            $this->amPm = $amPm;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getTime()
        {
            return $this->time;
        }

        /**
         * @param  mixed  $time
         * @return DailySchedule
         */
        public function setTime($time)
        {
            $this->time = $time;

            return $this;
        }
    }

    class EventSchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'event';
        }

        protected $quantity;

        protected $quantityType;

        /**
         * @return mixed
         */
        public function getQuantity()
        {
            return $this->quantity;
        }

        /**
         * @param  mixed  $quantity
         * @return EventSchedule
         */
        public function setQuantity($quantity)
        {
            $this->quantity = $quantity;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getQuantityType()
        {
            return $this->quantityType;
        }

        /**
         * @param  mixed  $quantityType
         * @return EventSchedule
         */
        public function setQuantityType($quantityType)
        {
            $this->quantityType = $quantityType;

            return $this;
        }
    }

    class MonthlySchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'monthly';
        }

        protected $days = [];

        protected $time;

        protected $amPm;

        /**
         * @return mixed
         */
        public function getAmPm()
        {
            return $this->amPm;
        }

        /**
         * @param  mixed  $amPm
         * @return MonthlySchedule
         */
        public function setAmPm($amPm)
        {
            $this->amPm = $amPm;

            return $this;
        }

        /**
         * @return array
         */
        public function getDays()
        {
            return $this->days;
        }

        /**
         * @param  array  $days
         * @return MonthlySchedule
         */
        public function setDays($days)
        {
            $this->days = $days;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getTime()
        {
            return $this->time;
        }

        /**
         * @param  mixed  $time
         * @return MonthlySchedule
         */
        public function setTime($time)
        {
            $this->time = $time;

            return $this;
        }
    }

    class NowSchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'now';
        }

        private $schedule_type;

        /**
         * @return mixed
         */
        public function getScheduleType()
        {
            return $this->schedule_type;
        }

        /**
         * @param  mixed  $schedule_type
         * @return NowSchedule
         */
        public function setScheduleType($schedule_type)
        {
            $this->schedule_type = $schedule_type;

            return $this;
        }
    }

    class OnceOffSchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'once_off';
        }

        protected $date;

        protected $time;

        protected $amPm;

        /**
         * @return mixed
         */
        public function getTime()
        {
            return $this->time;
        }

        /**
         * @param  mixed  $time
         * @return OnceOffSchedule
         */
        public function setTime($time)
        {
            $this->time = $time;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getAmPm()
        {
            return $this->amPm;
        }

        /**
         * @param  mixed  $amPm
         * @return OnceOffSchedule
         */
        public function setAmPm($amPm)
        {
            $this->amPm = $amPm;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getDate()
        {
            return $this->date;
        }

        /**
         * @param  mixed  $date
         * @return OnceOffSchedule
         */
        public function setDate($date)
        {
            $this->date = $date;

            return $this;
        }
    }

    class WeeklySchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'weekly';
        }

        protected $days = [];

        protected $time;

        protected $amPm;

        /**
         * @return mixed
         */
        public function getAmPm()
        {
            return $this->amPm;
        }

        /**
         * @param  mixed  $amPm
         * @return WeeklySchedule
         */
        public function setAmPm($amPm)
        {
            $this->amPm = $amPm;

            return $this;
        }

        /**
         * @return array
         */
        public function getDays()
        {
            return $this->days;
        }

        /**
         * @param  array  $days
         * @return WeeklySchedule
         */
        public function setDays($days)
        {
            $this->days = $days;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getTime()
        {
            return $this->time;
        }

        /**
         * @param  mixed  $time
         * @return WeeklySchedule
         */
        public function setTime($time)
        {
            $this->time = $time;

            return $this;
        }
    }
}

namespace Model\Crm{
    class Schedule
    {
        const TYPE_NOW = 'now';

        const TYPE_ONCE_OFF = 'once_off';

        const TYPE_DAILY = 'daily';

        const TYPE_WEEKLY = 'weekly';

        const TYPE_MONTHLY = 'monthly';

        const TYPE_EVENT = 'event';

        const TYPE_ACTION = 'action';

        protected $type;

        protected $conditions;

        /**
         * @return mixed
         */
        public function getConditions()
        {
            return $this->conditions;
        }

        /**
         * @param  mixed  $conditions
         * @return Schedule
         */
        public function setConditions($conditions)
        {
            $this->conditions = $conditions;

            return $this;
        }

        /**
         * @return string
         */
        public function getType()
        {
            return $this->type;
        }

        /**
         * @param  string  $type
         * @return ActionSchedule
         */
        public function setType($type)
        {
            $this->type = $type;

            return $this;
        }

        public function __construct()
        {
            $this->conditions = [];
        }
    }

    class ActionSchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'action';
        }

        protected $action;

        /**
         * @return mixed
         */
        public function getAction()
        {
            return $this->action;
        }

        /**
         * @param  mixed  $action
         * @return ActionSchedule
         */
        public function setAction($action)
        {
            $this->action = $action;

            return $this;
        }
    }

    class DailySchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'daily';
        }

        protected $time;

        protected $amPm;

        /**
         * @return mixed
         */
        public function getAmPm()
        {
            return $this->amPm;
        }

        /**
         * @param  mixed  $amPm
         * @return DailySchedule
         */
        public function setAmPm($amPm)
        {
            $this->amPm = $amPm;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getTime()
        {
            return $this->time;
        }

        /**
         * @param  mixed  $time
         * @return DailySchedule
         */
        public function setTime($time)
        {
            $this->time = $time;

            return $this;
        }
    }

    class EventSchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'event';
        }

        protected $quantity;

        protected $quantityType;

        /**
         * @return mixed
         */
        public function getQuantity()
        {
            return $this->quantity;
        }

        /**
         * @param  mixed  $quantity
         * @return EventSchedule
         */
        public function setQuantity($quantity)
        {
            $this->quantity = $quantity;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getQuantityType()
        {
            return $this->quantityType;
        }

        /**
         * @param  mixed  $quantityType
         * @return EventSchedule
         */
        public function setQuantityType($quantityType)
        {
            $this->quantityType = $quantityType;

            return $this;
        }
    }

    class MonthlySchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'monthly';
        }

        protected $days = [];

        protected $time;

        protected $amPm;

        /**
         * @return mixed
         */
        public function getAmPm()
        {
            return $this->amPm;
        }

        /**
         * @param  mixed  $amPm
         * @return MonthlySchedule
         */
        public function setAmPm($amPm)
        {
            $this->amPm = $amPm;

            return $this;
        }

        /**
         * @return array
         */
        public function getDays()
        {
            return $this->days;
        }

        /**
         * @param  array  $days
         * @return MonthlySchedule
         */
        public function setDays($days)
        {
            $this->days = $days;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getTime()
        {
            return $this->time;
        }

        /**
         * @param  mixed  $time
         * @return MonthlySchedule
         */
        public function setTime($time)
        {
            $this->time = $time;

            return $this;
        }
    }

    class NowSchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'now';
        }

        private $schedule_type;

        /**
         * @return mixed
         */
        public function getScheduleType()
        {
            return $this->schedule_type;
        }

        /**
         * @param  mixed  $schedule_type
         * @return NowSchedule
         */
        public function setScheduleType($schedule_type)
        {
            $this->schedule_type = $schedule_type;

            return $this;
        }
    }

    class OnceOffSchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'once_off';
        }

        protected $date;

        protected $time;

        protected $amPm;

        /**
         * @return mixed
         */
        public function getTime()
        {
            return $this->time;
        }

        /**
         * @param  mixed  $time
         * @return OnceOffSchedule
         */
        public function setTime($time)
        {
            $this->time = $time;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getAmPm()
        {
            return $this->amPm;
        }

        /**
         * @param  mixed  $amPm
         * @return OnceOffSchedule
         */
        public function setAmPm($amPm)
        {
            $this->amPm = $amPm;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getDate()
        {
            return $this->date;
        }

        /**
         * @param  mixed  $date
         * @return OnceOffSchedule
         */
        public function setDate($date)
        {
            $this->date = $date;

            return $this;
        }
    }

    class WeeklySchedule extends Schedule
    {
        public function __construct()
        {
            $this->type = 'weekly';
        }

        protected $days = [];

        protected $time;

        protected $amPm;

        /**
         * @return mixed
         */
        public function getAmPm()
        {
            return $this->amPm;
        }

        /**
         * @param  mixed  $amPm
         * @return WeeklySchedule
         */
        public function setAmPm($amPm)
        {
            $this->amPm = $amPm;

            return $this;
        }

        /**
         * @return array
         */
        public function getDays()
        {
            return $this->days;
        }

        /**
         * @param  array  $days
         * @return WeeklySchedule
         */
        public function setDays($days)
        {
            $this->days = $days;

            return $this;
        }

        /**
         * @return mixed
         */
        public function getTime()
        {
            return $this->time;
        }

        /**
         * @param  mixed  $time
         * @return WeeklySchedule
         */
        public function setTime($time)
        {
            $this->time = $time;

            return $this;
        }
    }
}
